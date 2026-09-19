<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\Payment;

class CodGateway extends AbstractPaymentGateway
{
    public function code(): string { return 'cod'; }
    public function name(): string { return 'Cash on Delivery'; }
    public function supports(): array { return []; }

    public function credentialSchema(): array
    {
        return [
            'max_amount' => ['label' => 'Max COD Amount', 'type' => 'text', 'required' => false, 'help' => 'Reject COD above this amount.'],
        ];
    }

    public function testConnection(): array
    {
        return ['ok' => true, 'message' => 'No external service — always available.'];
    }

    public function createCheckout(Order $order, Payment $payment): array
    {
        $max = (float) ($this->cred('max_amount') ?? $this->config('max_amount') ?? 0);
        if ($max > 0 && (float) $order->total > $max) {
            throw new \RuntimeException("COD not allowed above ₹{$max} — please choose an online payment method.");
        }
        $payment->update([
            'payment_id' => $order->order_no,
            'status' => Payment::STATUS_PENDING,
        ]);
        return [
            'gateway' => $this->code(),
            'gateway_order_id' => $order->order_no,
            'amount' => (int) round($order->total * 100),
            'currency' => 'INR',
            'sdk_config' => null,
        ];
    }

    public function verifyCallback(Order $order, Payment $payment, array $callbackPayload): array
    {
        return ['payment_id' => $order->order_no, 'gateway_order_id' => $order->order_no];
    }

    public function verifyWebhook(string $rawBody, array $headers): bool
    {
        return false;
    }

    public function refund(Payment $payment, float $amount, ?string $reason = null): array
    {
        throw new \RuntimeException('COD cannot be refunded online; process manually.');
    }
}
