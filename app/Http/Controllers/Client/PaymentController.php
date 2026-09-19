<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Notifications\OrderPlaced;
use App\Services\CouponService;
use App\Services\Payment\PaymentGatewayManager;
use App\Services\Payment\PaymentReconciler;
use App\Services\Payment\RazorpayGateway;
use App\Services\ShipmentService;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

class PaymentController extends Controller
{
    public function __construct(
        public PaymentGatewayManager $gatewayManager,
        public PaymentReconciler     $reconciler,
    ) {}

    /**
     * Resolve the raw Razorpay SDK client with the same credential precedence
     * used everywhere else (admin DB row → .env fallback). Throws when neither
     * is configured — caller MUST wrap and translate that to a 5xx response.
     */
    private function razorpayApi(): Api
    {
        try {
            $gateway = $this->gatewayManager->forCode('razorpay');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            throw new \RuntimeException('Razorpay gateway is not enabled in admin.');
        }
        if (! $gateway instanceof RazorpayGateway) {
            throw new \RuntimeException('Unexpected gateway resolution.');
        }
        // Pull the same key/secret the gateway adapter would use — we need
        // the raw SDK here because signature verification is a stateless
        // helper (verifyPaymentSignature / payment fetch).
        $creds = (array) $gateway->getModel()->credentials;
        $key    = $creds['key_id']     ?? config('services.razorpay.key');
        $secret = $creds['key_secret'] ?? config('services.razorpay.secret');
        if (empty($key) || empty($secret)) {
            throw new \RuntimeException('Razorpay credentials not configured.');
        }
        return new Api($key, $secret);
    }

    public function razorpayCallback(Request $request)
    {
        $customerId = Auth::guard('customer')->id();
        if (empty($customerId)) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $request->validate([
            'order_id' => 'required|integer',
            'razorpay_order_id' => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature' => 'required|string',
        ]);

        $order = Order::where('id', $request->order_id)
            ->where('customer_id', $customerId)
            ->first();

        if (empty($order)) {
            Log::warning('Razorpay callback: order not found or ownership mismatch', [
                'customer_id' => $customerId,
                'order_id' => $request->order_id,
            ]);
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        $payment = Payment::where('order_id', $order->id)
            ->where('type', 'razorpay')
            ->first();

        if (empty($payment)) {
            return response()->json(['success' => false, 'message' => 'Payment record missing.'], 404);
        }

        if ($payment->status === Payment::STATUS_PAID) {
            return response()->json(['success' => true, 'message' => 'Already paid.']);
        }

        // Resolve the SDK client (admin DB → .env fallback). If neither is
        // configured we surface a specific 503 instead of masking as a signature
        // failure — otherwise ops has no way to tell "wrong signature" (fraud)
        // from "gateway not configured" (setup bug).
        try {
            $api = $this->razorpayApi();
        } catch (\RuntimeException $e) {
            Log::error('Razorpay callback: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'customer_id' => $customerId,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Payment gateway is not configured. Please contact support with your order id ' . $order->order_no . '.',
            ], 503);
        }

        try {
            $api->utility->verifyPaymentSignature([
                'razorpay_order_id' => $request->razorpay_order_id,
                'razorpay_payment_id' => $request->razorpay_payment_id,
                'razorpay_signature' => $request->razorpay_signature,
            ]);

            // Server-side amount reconciliation — do not trust the client to
            // report the paid amount/order id. Fetch from Razorpay and compare
            // against what we stored when we created the local order.
            $gatewayPayment = $api->payment->fetch($request->razorpay_payment_id);
            $expectedAmount = (int) round(((float) $order->total) * 100);
            if ((int) $gatewayPayment->amount !== $expectedAmount
                || strtoupper((string) $gatewayPayment->currency) !== 'INR'
                || (string) $gatewayPayment->order_id !== (string) $payment->payment_id) {
                Log::warning('Razorpay amount/order mismatch on callback', [
                    'order_id' => $order->id,
                    'expected' => $expectedAmount,
                    'received' => (int) $gatewayPayment->amount,
                    'gateway_order_id' => $gatewayPayment->order_id ?? null,
                    'local_payment_id' => $payment->payment_id,
                ]);
                return response()->json(['success' => false, 'message' => 'Payment amount mismatch.'], 422);
            }

            $confirmed = $this->reconciler->confirm($payment->id, $order->id, [
                'razorpay_payment_id' => $request->razorpay_payment_id,
                'source_meta_key' => 'callback_at',
                'history_source' => OrderStatusHistory::SOURCE_SYSTEM,
                'history_comment' => 'Payment captured via Razorpay callback.',
            ]);

            if ($confirmed) {
                Session::forget('cart');
                app(CouponService::class)->consume();
                $this->sendOrderPlacedOnce($order);
            }

            return response()->json(['success' => true]);
        } catch (SignatureVerificationError $e) {
            Log::warning('Razorpay signature verification failed', [
                'order_id' => $request->order_id,
                'customer_id' => $customerId,
            ]);
            return response()->json(['success' => false, 'message' => 'Payment verification failed.'], 401);
        } catch (\Exception $e) {
            Log::error('Razorpay callback error: ' . $e->getMessage(), [
                'order_id' => $request->order_id,
            ]);
            return response()->json(['success' => false, 'message' => 'Payment processing error.'], 500);
        }
    }

    /**
     * Server-to-server webhook from Razorpay.
     */
    public function razorpayWebhook(Request $request)
    {
        $signature = $request->header('X-Razorpay-Signature', '');
        $body = $request->getContent();

        // Resolve webhook secret from admin PaymentGateway config first, then
        // .env fallback — same precedence as callback + createCheckout.
        $secret = null;
        try {
            $gateway = $this->gatewayManager->forCode('razorpay');
            $creds = (array) $gateway->getModel()->credentials;
            $secret = $creds['webhook_secret'] ?? config('services.razorpay.webhook_secret');
        } catch (\Throwable) {
            $secret = config('services.razorpay.webhook_secret');
        }

        if (empty($secret)) {
            Log::error('Razorpay webhook secret is not configured.');
            return response()->json(['success' => false], 500);
        }

        $expected = hash_hmac('sha256', $body, $secret);
        if (! hash_equals($expected, $signature)) {
            Log::warning('Razorpay webhook: invalid signature');
            return response()->json(['success' => false], 401);
        }

        $payload = json_decode($body, true);
        if (! is_array($payload)) {
            Log::warning('Razorpay webhook: malformed JSON body');
            return response()->json(['success' => false], 400);
        }

        $event = $payload['event'] ?? '';
        $eventId = $payload['id'] ?? $payload['event_id'] ?? null;
        $entity = $payload['payload']['payment']['entity'] ?? [];
        $refundEntity = $payload['payload']['refund']['entity'] ?? [];
        $razorpayOrderId = $entity['order_id'] ?? null;
        $razorpayPaymentId = $entity['id'] ?? ($refundEntity['payment_id'] ?? null);

        $payment = null;
        if ($razorpayOrderId) {
            $payment = Payment::where('payment_id', $razorpayOrderId)->first();
        }
        if (! $payment && $razorpayPaymentId) {
            $payment = Payment::where('payment_id', $razorpayPaymentId)->first();
        }
        if (! $payment) {
            Log::info('Razorpay webhook: no matching payment record', [
                'razorpay_order_id' => $razorpayOrderId,
                'razorpay_payment_id' => $razorpayPaymentId,
                'event' => $event,
            ]);
            return response()->json(['success' => true]);
        }

        $order = Order::find($payment->order_id);
        if (! $order) {
            return response()->json(['success' => true]);
        }

        // Idempotency: dedupe by Razorpay event id / refund id. Once a given
        // event has been processed we early-return successfully so Razorpay
        // stops retrying.
        $meta = (array) $payment->meta;
        $processedEvents = (array) ($meta['webhook_events'] ?? []);
        $dedupeKey = $event . ':' . ($eventId ?: ($refundEntity['id'] ?? $razorpayPaymentId ?? ''));
        if (in_array($dedupeKey, $processedEvents, true)) {
            return response()->json(['success' => true]);
        }

        if ($event === 'payment.captured') {
            $this->reconciler->confirm($payment->id, $order->id, [
                'razorpay_payment_id' => $razorpayPaymentId,
                'source_meta_key' => 'captured',
                'source_meta_value' => $entity,
                'history_source' => OrderStatusHistory::SOURCE_WEBHOOK,
                'history_comment' => 'Payment captured (webhook).',
                'dedupe_key' => $dedupeKey,
                'dedupe_scope' => $processedEvents,
            ]);
            // After confirming, ensure notification fires exactly once even
            // if callback + webhook race — sendOrderPlacedOnce() is guarded.
            $this->sendOrderPlacedOnce($order->fresh());
        } elseif ($event === 'payment.failed') {
            DB::transaction(function () use ($payment, $order, $entity, $dedupeKey, $processedEvents) {
                $locked = Payment::whereKey($payment->id)->lockForUpdate()->first();
                if (! $locked) return;

                // Do not regress a successful payment to FAILED.
                if ($locked->status === Payment::STATUS_PAID) {
                    return;
                }

                $newMeta = (array) $locked->meta;
                $newMeta['failed'] = $entity;
                $newMeta['webhook_events'] = array_values(array_unique(array_merge(
                    $processedEvents,
                    [$dedupeKey]
                )));

                $locked->update([
                    'status' => Payment::STATUS_FAILED,
                    'failure_reason' => $entity['error_description'] ?? ($entity['error_reason'] ?? null),
                    'meta' => $newMeta,
                ]);
                $order->status = Order::CANCELLED;
                $order->save();
                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'status' => Order::CANCELLED,
                    'source' => OrderStatusHistory::SOURCE_WEBHOOK,
                    'comment' => 'Payment failed (webhook).',
                ]);
            });
        } elseif ($event === 'refund.processed' || $event === 'refund.created') {
            DB::transaction(function () use ($payment, $order, $refundEntity, $dedupeKey, $processedEvents) {
                $locked = Payment::whereKey($payment->id)->lockForUpdate()->first();
                if (! $locked) return;

                $refundId = $refundEntity['id'] ?? null;
                $refundedAmount = ($refundEntity['amount'] ?? 0) / 100;

                // Additional idempotency at the refund-id level so a retried
                // refund event does not stack the refunded amount.
                $processedRefunds = (array) (((array) $locked->meta)['processed_refund_ids'] ?? []);
                if ($refundId && in_array($refundId, $processedRefunds, true)) {
                    return;
                }

                $newTotal = (float) $locked->refunded_amount + $refundedAmount;
                if ($newTotal > (float) $locked->amount + 0.01) {
                    Log::warning('Razorpay refund exceeds payment amount', [
                        'payment_id' => $locked->id,
                        'payment_amount' => $locked->amount,
                        'attempted_total' => $newTotal,
                        'refund_id' => $refundId,
                    ]);
                    // Cap at payment amount; log the discrepancy for ops.
                    $newTotal = (float) $locked->amount;
                }

                $isFull = $newTotal >= (float) $locked->amount;

                $newMeta = (array) $locked->meta;
                $newMeta['refund'] = $refundEntity;
                if ($refundId) {
                    $newMeta['processed_refund_ids'] = array_values(array_unique(array_merge($processedRefunds, [$refundId])));
                }
                $newMeta['webhook_events'] = array_values(array_unique(array_merge($processedEvents, [$dedupeKey])));

                $locked->update([
                    'refund_id'       => $refundId ?? $locked->refund_id,
                    'refunded_amount' => $newTotal,
                    'refunded_at'     => now(),
                    'status'          => $isFull ? Payment::STATUS_REFUNDED : Payment::STATUS_PARTIALLY_REFUNDED,
                    'meta'            => $newMeta,
                ]);
                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'status'   => $order->status,
                    'source'   => OrderStatusHistory::SOURCE_WEBHOOK,
                    'comment'  => 'Refund ' . ($isFull ? 'processed (full)' : 'processed (partial)') . ' ₹' . number_format($refundedAmount, 2),
                ]);
            });
        }

        return response()->json(['success' => true]);
    }

    /**
     * Fire OrderPlaced email + SMS exactly once, guarded by an atomic flag
     * on the order's payment.meta so callback + webhook racing does not
     * produce duplicate customer notifications.
     */
    protected function sendOrderPlacedOnce(Order $order): void
    {
        $payment = Payment::where('order_id', $order->id)->first();
        if (! $payment) return;
        $meta = (array) $payment->meta;
        if (! empty($meta['order_placed_notified_at'])) {
            return;
        }

        // Set the sentinel first so a concurrent request loses the race.
        $affected = Payment::whereKey($payment->id)
            ->whereRaw("(json_extract(meta, '$.order_placed_notified_at') IS NULL OR json_extract(meta, '$.order_placed_notified_at') = 'null')")
            ->update([
                'meta' => json_encode(array_merge($meta, ['order_placed_notified_at' => now()->toIso8601String()])),
            ]);
        if ($affected < 1) {
            return; // another process already claimed the notification
        }

        try {
            $customer = $order->customer;
            if ($customer) {
                $customer->notify(new OrderPlaced($order));
                if ($order->shipping_phone) {
                    app(SmsService::class)->send(
                        $order->shipping_phone,
                        "Hi {$customer->name}, payment received for order #{$order->order_no} (Rs " . number_format((float) $order->total, 2) . "). We will notify you when it ships. - " . config('app.name')
                    );
                }
            }
        } catch (\Exception $e) {
            Log::warning('OrderPlaced notification failed after callback: ' . $e->getMessage());
        }
    }

    /**
     * Shiprocket status-update webhook. Called by Shiprocket on tracking changes.
     * Public endpoint — validated via a shared token in the request query/header.
     */
    public function shiprocketWebhook(Request $request, ShipmentService $shipmentService)
    {
        $expected = config('services.shiprocket.webhook_token');
        // Prefer HMAC-SHA256 signature over the request body when a webhook
        // secret is configured; fall back to the legacy shared-token header
        // for backwards compatibility with existing Shiprocket setups.
        $secret = config('services.shiprocket.webhook_secret');
        $signature = $request->header('X-Shiprocket-Signature', '');
        if (! empty($secret) && ! empty($signature)) {
            $computed = hash_hmac('sha256', $request->getContent(), $secret);
            if (! hash_equals($computed, $signature)) {
                Log::warning('Shiprocket webhook: invalid signature');
                return response()->json(['success' => false], 401);
            }
        } else {
            // Legacy shared-token path — accept only via header (never query
            // string, which leaks into access logs / referers).
            $provided = $request->header('X-Api-Key', '');
            if (empty($expected) || ! is_string($provided) || $provided === '' || ! hash_equals($expected, $provided)) {
                Log::warning('Shiprocket webhook: invalid token');
                return response()->json(['success' => false], 401);
            }
        }

        try {
            $payload = $request->all();
            $shipmentService->handleTrackingWebhook($payload);
        } catch (\Throwable $e) {
            Log::error('Shiprocket webhook handling error: ' . $e->getMessage());
        }
        return response()->json(['success' => true]);
    }
}
