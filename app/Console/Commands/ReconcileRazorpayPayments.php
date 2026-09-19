<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Notifications\OrderPlaced;
use App\Services\CouponService;
use App\Services\Payment\PaymentGatewayManager;
use App\Services\Payment\PaymentReconciler;
use App\Services\Payment\RazorpayGateway;
use App\Services\SmsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api;

/**
 * Catches up on Razorpay payments that were captured on Razorpay's side
 * but never marked PAID locally (usually because the callback crashed or
 * the webhook was misconfigured). Idempotent — safe to run repeatedly or
 * on a schedule.
 *
 * Typical schedule (Kernel.php):  ->everyFifteenMinutes()->withoutOverlapping();
 */
class ReconcileRazorpayPayments extends Command
{
    protected $signature = 'payments:reconcile-razorpay
                            {--payment= : Only reconcile a specific payment id}
                            {--dry-run : Show what would happen without touching the DB}
                            {--stale-minutes=15 : Skip payments created in the last N minutes (customer may still be in checkout)}';

    protected $description = 'Reconcile pending Razorpay payments against the Razorpay API and confirm any that were captured.';

    public function handle(PaymentGatewayManager $manager, PaymentReconciler $reconciler): int
    {
        $dry = (bool) $this->option('dry-run');
        $staleMinutes = (int) $this->option('stale-minutes');

        try {
            $gateway = $manager->forCode('razorpay');
        } catch (\Throwable) {
            $this->error('Razorpay gateway is not enabled in admin. Nothing to do.');
            return self::FAILURE;
        }
        if (! $gateway instanceof RazorpayGateway) {
            $this->error('Unexpected gateway type: ' . get_class($gateway));
            return self::FAILURE;
        }

        $creds = (array) $gateway->getModel()->credentials;
        $key    = $creds['key_id']     ?? config('services.razorpay.key');
        $secret = $creds['key_secret'] ?? config('services.razorpay.secret');
        if (empty($key) || empty($secret)) {
            $this->error('Razorpay credentials not configured.');
            return self::FAILURE;
        }
        $api = new Api($key, $secret);

        $query = Payment::query()
            ->where('type', 'razorpay')
            ->where('status', 'pending');

        if ($this->option('payment')) {
            $query->whereKey((int) $this->option('payment'));
        } else {
            // Give the customer time to finish checkout before we snoop.
            $query->where('created_at', '<=', now()->subMinutes($staleMinutes));
        }

        $rows = $query->get();

        if ($rows->isEmpty()) {
            $this->info('No pending Razorpay payments to reconcile.');
            return self::SUCCESS;
        }

        $this->line(sprintf('Checking %d pending payment(s)...', $rows->count()));
        $this->newLine();

        $confirmed       = 0;
        $stillPending    = 0;
        $failedOnGateway = 0;
        $errors          = 0;

        foreach ($rows as $payment) {
            $order = Order::find($payment->order_id);
            if (! $order) {
                $this->warn(sprintf('  payment#%d: order#%d missing — skipping',
                    $payment->id, $payment->order_id));
                $errors++;
                continue;
            }

            $razorpayOrderId = $payment->payment_id; // stored at creation time
            if (empty($razorpayOrderId) || ! str_starts_with($razorpayOrderId, 'order_')) {
                $this->warn(sprintf('  payment#%d: no razorpay order id on record — skipping',
                    $payment->id));
                $errors++;
                continue;
            }

            try {
                $gatewayOrder = $api->order->fetch($razorpayOrderId);
                $status = $gatewayOrder->status ?? 'unknown';
                $payments = $api->order->fetch($razorpayOrderId)->payments();
            } catch (\Throwable $e) {
                $this->error(sprintf('  payment#%d (order#%d): Razorpay API error: %s',
                    $payment->id, $order->id, $this->stripSecrets($e->getMessage())));
                $errors++;
                continue;
            }

            // Find the first captured payment on this Razorpay order.
            $captured = null;
            foreach ($payments->items ?? [] as $p) {
                if (($p->status ?? '') === 'captured') { $captured = $p; break; }
            }

            if (! $captured) {
                $this->line(sprintf('  payment#%d (order#%d): Razorpay order status=%s, no captured payment (leaving pending)',
                    $payment->id, $order->id, $status));
                $stillPending++;
                continue;
            }

            // Amount reconciliation — refuse to mark PAID if the captured amount
            // doesn't match what we expect (guards against a customer paying a
            // different amount, currency mismatch, or wrong Razorpay account).
            $expectedPaise = (int) round(((float) $order->total) * 100);
            $capturedPaise = (int) ($captured->amount ?? 0);
            $currency      = strtoupper((string) ($captured->currency ?? ''));
            if ($capturedPaise !== $expectedPaise || $currency !== 'INR') {
                $this->error(sprintf('  payment#%d (order#%d): amount/currency mismatch — expected %d INR, got %d %s. Skipping.',
                    $payment->id, $order->id, $expectedPaise, $capturedPaise, $currency));
                $failedOnGateway++;
                continue;
            }

            if ($dry) {
                $this->info(sprintf('  payment#%d (order#%d): [DRY-RUN] would confirm captured payment %s (₹%s)',
                    $payment->id, $order->id, $captured->id, number_format($capturedPaise / 100, 2)));
                $confirmed++;
                continue;
            }

            $wasConfirmed = $reconciler->confirm($payment->id, $order->id, [
                'razorpay_payment_id' => $captured->id,
                'source_meta_key'     => 'reconciled_at',
                'history_source'      => OrderStatusHistory::SOURCE_SYSTEM,
                'history_comment'     => 'Payment captured — reconciled from Razorpay API.',
            ]);

            if ($wasConfirmed) {
                $confirmed++;
                $this->info(sprintf('  payment#%d (order#%d): confirmed (razorpay_payment_id=%s, ₹%s)',
                    $payment->id, $order->id, $captured->id, number_format($capturedPaise / 100, 2)));
                $this->sendPostConfirmSideEffects($order->fresh());
            } else {
                // Race — another process (webhook, callback) confirmed it first.
                $this->line(sprintf('  payment#%d (order#%d): already confirmed by another channel',
                    $payment->id, $order->id));
            }
        }

        $this->newLine();
        $this->line(str_repeat('-', 60));
        $this->line(sprintf('Confirmed:      %d', $confirmed) . ($dry ? '  (dry-run — no writes)' : ''));
        $this->line(sprintf('Still pending:  %d  (no capture on gateway yet)', $stillPending));
        $this->line(sprintf('Mismatched:     %d', $failedOnGateway));
        $this->line(sprintf('Errors:         %d', $errors));

        return $failedOnGateway === 0 && $errors === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Post-confirmation side effects — customer notification, cart cleanup,
     * coupon consumption. Failures here are logged but non-fatal (the payment
     * is already correctly reconciled at this point).
     */
    private function sendPostConfirmSideEffects(Order $order): void
    {
        try {
            app(CouponService::class)->consume();
        } catch (\Throwable $e) {
            Log::warning('Coupon consume failed during reconciliation: ' . $e->getMessage());
        }

        $payment = Payment::where('order_id', $order->id)->first();
        if (! $payment) return;

        $meta = (array) $payment->meta;
        if (! empty($meta['order_placed_notified_at'])) return;

        try {
            $customer = $order->customer;
            if (! $customer) return;

            $customer->notify(new OrderPlaced($order));
            if ($order->shipping_phone) {
                app(SmsService::class)->send(
                    $order->shipping_phone,
                    sprintf('Hi %s, payment received for order #%s (Rs %s). We will notify you when it ships. - %s',
                        $customer->name, $order->order_no,
                        number_format((float) $order->total, 2),
                        config('app.name'))
                );
            }
            $payment->update(['meta' => array_merge($meta, [
                'order_placed_notified_at' => now()->toIso8601String(),
            ])]);
        } catch (\Throwable $e) {
            Log::warning('OrderPlaced notify failed during reconciliation: ' . $e->getMessage());
        }
    }

    private function stripSecrets(string $msg): string
    {
        return preg_replace('/rzp_(test|live)_\w+/', 'rzp_***', $msg) ?? $msg;
    }
}
