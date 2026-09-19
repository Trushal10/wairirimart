<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

class RazorpayGateway extends AbstractPaymentGateway
{
    public function code(): string { return 'razorpay'; }
    public function name(): string { return 'Razorpay'; }
    public function supports(): array { return ['refund', 'partial_refund', 'webhook', 'upi', 'cards', 'netbanking', 'wallets']; }

    public function credentialSchema(): array
    {
        return [
            'key_id' => ['label' => 'Key ID', 'type' => 'text', 'required' => true, 'help' => 'Publishable key from Razorpay dashboard.'],
            'key_secret' => ['label' => 'Key Secret', 'type' => 'password', 'required' => true, 'help' => 'Server-side secret. Never exposed.'],
            'webhook_secret' => ['label' => 'Webhook Secret', 'type' => 'password', 'required' => false, 'help' => 'Used to verify Razorpay webhook signatures.'],
        ];
    }

    public function testConnection(): array
    {
        $key = $this->cred('key_id');
        $secret = $this->cred('key_secret');
        if (empty($key) || empty($secret)) {
            return ['ok' => false, 'message' => 'Missing key_id or key_secret.'];
        }
        try {
            (new Api($key, $secret))->order->all(['count' => 1]);
            return ['ok' => true, 'message' => 'Authenticated with Razorpay.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $this->safeError($e)];
        }
    }

    public function createCheckout(Order $order, Payment $payment): array
    {
        $api = $this->api();
        $rzOrder = $api->order->create([
            'receipt' => $order->order_no,
            'amount' => (int) round($order->total * 100),
            'currency' => 'INR',
            'payment_capture' => 1,
            'notes' => ['order_no' => $order->order_no, 'customer_id' => (string) $order->customer_id],
        ]);
        $payment->update([
            'payment_id' => $rzOrder['id'],
            'meta' => array_merge((array) $payment->meta, ['gateway_order' => $rzOrder->toArray()]),
        ]);
        return [
            'gateway' => $this->code(),
            'gateway_order_id' => $rzOrder['id'],
            'amount' => $rzOrder['amount'],
            'currency' => $rzOrder['currency'],
            'sdk_config' => [
                'key' => $this->cred('key_id'),
                'order_id' => $rzOrder['id'],
                'name' => config('app.name'),
                'description' => 'Order #' . $order->order_no,
            ],
        ];
    }

    public function verifyCallback(Order $order, Payment $payment, array $callbackPayload): array
    {
        $required = ['razorpay_order_id', 'razorpay_payment_id', 'razorpay_signature'];
        foreach ($required as $key) {
            if (empty($callbackPayload[$key])) {
                throw new \InvalidArgumentException("Missing {$key}");
            }
        }
        try {
            $this->api()->utility->verifyPaymentSignature([
                'razorpay_order_id' => $callbackPayload['razorpay_order_id'],
                'razorpay_payment_id' => $callbackPayload['razorpay_payment_id'],
                'razorpay_signature' => $callbackPayload['razorpay_signature'],
            ]);
        } catch (SignatureVerificationError $e) {
            throw new \RuntimeException('Signature verification failed.');
        }
        return [
            'payment_id' => $callbackPayload['razorpay_payment_id'],
            'gateway_order_id' => $callbackPayload['razorpay_order_id'],
        ];
    }

    public function verifyWebhook(string $rawBody, array $headers): bool
    {
        $secret = $this->cred('webhook_secret');
        if (empty($secret)) {
            return false;
        }
        $sig = $headers['x-razorpay-signature'][0] ?? $headers['X-Razorpay-Signature'][0] ?? '';
        return is_string($sig) && hash_equals(hash_hmac('sha256', $rawBody, $secret), $sig);
    }

    public function refund(Payment $payment, float $amount, ?string $reason = null): array
    {
        try {
            $refund = $this->api()->payment->fetch($payment->payment_id)->refund([
                'amount' => (int) round($amount * 100),
                'speed' => 'normal',
                'notes' => [
                    'reason' => $reason ?? 'Admin initiated refund',
                    'order_id' => (string) $payment->order_id,
                ],
            ]);
            return $refund->toArray();
        } catch (\Throwable $e) {
            Log::error('Razorpay refund failed: ' . $e->getMessage(), ['payment_id' => $payment->id]);
            throw new \RuntimeException($this->safeError($e));
        }
    }

    private function api(): Api
    {
        $key = $this->cred('key_id') ?: config('services.razorpay.key');
        $secret = $this->cred('key_secret') ?: config('services.razorpay.secret');
        if (empty($key) || empty($secret)) {
            throw new \RuntimeException('Razorpay credentials not configured.');
        }
        return new Api($key, $secret);
    }

    private function safeError(\Throwable $e): string
    {
        $msg = $e->getMessage();
        // strip any potential secret leakage
        return preg_replace('/rzp_(test|live)_\w+/', 'rzp_***', (string) $msg);
    }
}
