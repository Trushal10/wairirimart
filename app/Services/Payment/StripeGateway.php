<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Direct HTTP integration against api.stripe.com. Uses PaymentIntents.
 * Requires the admin to enter the secret key via the admin panel.
 */
class StripeGateway extends AbstractPaymentGateway
{
    private const BASE = 'https://api.stripe.com/v1';

    public function code(): string { return 'stripe'; }
    public function name(): string { return 'Stripe'; }
    public function supports(): array { return ['refund', 'partial_refund', 'webhook', 'cards', 'wallets']; }

    public function credentialSchema(): array
    {
        return [
            'publishable_key' => ['label' => 'Publishable Key', 'type' => 'text', 'required' => true, 'help' => 'pk_test_… or pk_live_… — safe to expose on frontend.'],
            'secret_key' => ['label' => 'Secret Key', 'type' => 'password', 'required' => true, 'help' => 'sk_test_… or sk_live_… — kept server-side.'],
            'webhook_secret' => ['label' => 'Webhook Signing Secret', 'type' => 'password', 'required' => false, 'help' => 'whsec_… — from Stripe → Developers → Webhooks.'],
        ];
    }

    public function testConnection(): array
    {
        $secret = $this->cred('secret_key');
        if (empty($secret)) return ['ok' => false, 'message' => 'Missing secret_key.'];
        try {
            $res = Http::withBasicAuth($secret, '')->timeout(10)->get(self::BASE . '/balance');
            return $res->successful()
                ? ['ok' => true, 'message' => 'Authenticated with Stripe.']
                : ['ok' => false, 'message' => 'Stripe rejected the credentials (' . $res->status() . ').'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function createCheckout(Order $order, Payment $payment): array
    {
        $secret = $this->cred('secret_key');
        if (empty($secret)) throw new \RuntimeException('Stripe secret_key missing.');

        $res = Http::withBasicAuth($secret, '')
            ->asForm()
            ->timeout(15)
            ->post(self::BASE . '/payment_intents', [
                'amount' => (int) round($order->total * 100),
                'currency' => strtolower($this->config('currency', 'inr')),
                'metadata' => [
                    'order_no' => $order->order_no,
                    'order_id' => (string) $order->id,
                ],
                'automatic_payment_methods' => ['enabled' => 'true'],
            ]);

        if (! $res->successful()) {
            Log::error('Stripe PI creation failed', ['body' => $res->body()]);
            throw new \RuntimeException('Stripe rejected the intent request.');
        }
        $intent = $res->json();

        $payment->update([
            'payment_id' => $intent['id'] ?? null,
            'meta' => array_merge((array) $payment->meta, ['gateway_intent' => $intent]),
        ]);

        return [
            'gateway' => $this->code(),
            'gateway_order_id' => $intent['id'] ?? null,
            'amount' => $intent['amount'] ?? 0,
            'currency' => strtoupper($intent['currency'] ?? 'INR'),
            'sdk_config' => [
                'publishable_key' => $this->cred('publishable_key'),
                'client_secret' => $intent['client_secret'] ?? null,
            ],
        ];
    }

    public function verifyCallback(Order $order, Payment $payment, array $callbackPayload): array
    {
        $secret = $this->cred('secret_key');
        $intentId = $callbackPayload['payment_intent'] ?? $payment->payment_id;
        if (empty($intentId)) {
            throw new \InvalidArgumentException('Missing payment_intent id.');
        }
        $res = Http::withBasicAuth($secret, '')->timeout(10)->get(self::BASE . '/payment_intents/' . $intentId);
        if (! $res->successful()) {
            throw new \RuntimeException('Stripe API error: ' . $res->status());
        }
        $intent = $res->json();
        if (($intent['status'] ?? '') !== 'succeeded') {
            throw new \RuntimeException('Payment not captured (status: ' . ($intent['status'] ?? 'unknown') . ').');
        }
        return ['payment_id' => $intent['id'], 'gateway_order_id' => $intent['id']];
    }

    public function verifyWebhook(string $rawBody, array $headers): bool
    {
        $secret = $this->cred('webhook_secret');
        if (empty($secret)) return false;
        $sig = $headers['stripe-signature'][0] ?? $headers['Stripe-Signature'][0] ?? '';
        if (! is_string($sig) || $sig === '') return false;
        // Signature is: t=<ts>,v1=<sig>,v0=<sig>
        $parts = [];
        foreach (explode(',', $sig) as $piece) {
            [$k, $v] = array_pad(explode('=', $piece, 2), 2, '');
            $parts[$k] = $v;
        }
        if (empty($parts['t']) || empty($parts['v1'])) return false;
        $signed = $parts['t'] . '.' . $rawBody;
        $expected = hash_hmac('sha256', $signed, $secret);
        return hash_equals($expected, $parts['v1']);
    }

    public function refund(Payment $payment, float $amount, ?string $reason = null): array
    {
        $secret = $this->cred('secret_key');
        $res = Http::withBasicAuth($secret, '')
            ->asForm()
            ->timeout(15)
            ->post(self::BASE . '/refunds', [
                'payment_intent' => $payment->payment_id,
                'amount' => (int) round($amount * 100),
                'reason' => $reason ? 'requested_by_customer' : 'requested_by_customer',
                'metadata' => ['reason' => $reason ?? ''],
            ]);
        if (! $res->successful()) {
            throw new \RuntimeException('Stripe refund failed: ' . $res->status());
        }
        return $res->json();
    }
}
