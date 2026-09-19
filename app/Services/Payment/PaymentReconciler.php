<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Shared payment-confirmation flow used by:
 *   - PaymentController::razorpayCallback  (customer returns from Razorpay modal)
 *   - PaymentController::razorpayWebhook   (server-to-server captured event)
 *   - payments:reconcile-razorpay command  (catch-up job for failed callbacks)
 *
 * All three paths must produce identical side effects (payment status,
 * order status, stock decrement, history row) so this is the ONE place
 * that logic lives.
 */
class PaymentReconciler
{
    /**
     * Confirm a payment, transition the order to CONFIRMED, and decrement stock.
     * Wraps the whole thing in a transaction + row-level locks so callback,
     * webhook, and CLI job can safely race without double-decrementing stock
     * or double-firing state changes.
     *
     * Returns true when THIS call was the transition; false when the payment
     * was already PAID (idempotent) or the payment/order rows are missing.
     *
     * @param array{
     *   razorpay_payment_id?: string|null,
     *   source_meta_key?: string,
     *   source_meta_value?: mixed,
     *   history_source?: string,
     *   history_comment?: string,
     *   dedupe_key?: string,
     *   dedupe_scope?: array<int,string>,
     * } $ctx
     */
    public function confirm(int $paymentId, int $orderId, array $ctx = []): bool
    {
        return DB::transaction(function () use ($paymentId, $orderId, $ctx) {
            $payment = Payment::whereKey($paymentId)->lockForUpdate()->first();
            $order   = Order::whereKey($orderId)->lockForUpdate()->first();

            if (! $payment || ! $order) {
                return false;
            }
            if ($payment->status === Payment::STATUS_PAID) {
                return false; // already confirmed on another channel
            }

            // The customer can cancel while a prepaid order is still pending,
            // so a capture can land on an order that no longer wants it —
            // they cancelled, then the gateway (or a retried webhook) came
            // back. Confirming here would silently un-cancel the order and
            // take stock for something nobody is going to ship. Record the
            // money and leave it for a human to refund.
            if ($order->status === Order::CANCELLED) {
                Log::warning('Payment captured for a cancelled order — needs manual refund.', [
                    'order_id'   => $order->id,
                    'order_no'   => $order->order_no,
                    'payment_id' => $payment->id,
                ]);

                return false;
            }

            $meta = (array) $payment->meta;
            if (! empty($ctx['source_meta_key'])) {
                $meta[$ctx['source_meta_key']] = $ctx['source_meta_value'] ?? now()->toIso8601String();
            }
            if (! empty($ctx['dedupe_key'])) {
                $processedEvents = (array) ($ctx['dedupe_scope'] ?? ($meta['webhook_events'] ?? []));
                $meta['webhook_events'] = array_values(array_unique(array_merge($processedEvents, [$ctx['dedupe_key']])));
            }

            $payment->update([
                'status'     => Payment::STATUS_PAID,
                'payment_id' => $ctx['razorpay_payment_id'] ?? $payment->payment_id,
                'meta'       => $meta,
            ]);
            $order->status = Order::CONFIRMED;
            $order->save();

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status'   => Order::CONFIRMED,
                'source'   => $ctx['history_source'] ?? OrderStatusHistory::SOURCE_SYSTEM,
                'comment'  => $ctx['history_comment'] ?? 'Payment captured.',
            ]);

            $this->decrementStockForOrder($order);

            return true;
        });
    }

    /**
     * Decrement stock for every line on an order. Variant items decrement the
     * variant only — the base products.stock column is 0 by design for variant
     * products and decrementing an unsigned BIGINT below zero throws
     * SQLSTATE[22003]. Single-SKU products still hit products.stock.
     *
     * Public so the COD path in OrderController (which decrements at
     * order-placement time, not at payment-confirmation time) can share it.
     * MUST be called from inside a transaction when concurrent orders are
     * possible.
     */
    public function decrementStockForOrder(Order $order): void
    {
        // COD decrements at placement and prepaid at capture, and a retried
        // webhook can arrive after either. Stamping the order is what stops the
        // same lines being taken out of inventory twice.
        if ($order->stock_committed_at !== null) {
            return;
        }

        foreach ($order->orderItems as $item) {
            $qty = (int) $item->quantity;
            if (! empty($item->product_variant_id)) {
                $variant = ProductVariant::whereKey($item->product_variant_id)->lockForUpdate()->first();
                if ($variant) {
                    $variant->decrement('stock', $qty);
                }
                continue;
            }
            $product = Product::whereKey($item->product_id)->lockForUpdate()->first();
            if ($product && ! $product->has_variants) {
                $product->decrement('stock', $qty);
            }
        }

        $order->forceFill(['stock_committed_at' => now()])->save();
    }

    /**
     * Put an order's stock back on the shelf.
     *
     * The mirror of decrementStockForOrder, and gated on the same stamp: an
     * order whose stock was never committed (a prepaid order abandoned before
     * payment) must not have inventory handed back to it, or every abandoned
     * checkout quietly inflates the catalogue.
     *
     * Returns true when stock actually moved. MUST be called from inside a
     * transaction — the caller is expected to be holding a lock on the order.
     */
    public function restoreStockForOrder(Order $order): bool
    {
        if ($order->stock_committed_at === null) {
            return false;
        }

        foreach ($order->orderItems as $item) {
            $qty = (int) $item->quantity;
            if (! empty($item->product_variant_id)) {
                $variant = ProductVariant::whereKey($item->product_variant_id)->lockForUpdate()->first();
                if ($variant) {
                    $variant->increment('stock', $qty);
                }
                continue;
            }
            $product = Product::whereKey($item->product_id)->lockForUpdate()->first();
            if ($product && ! $product->has_variants) {
                $product->increment('stock', $qty);
            }
        }

        $order->forceFill(['stock_committed_at' => null])->save();

        return true;
    }
}
