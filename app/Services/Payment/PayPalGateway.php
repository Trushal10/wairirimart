<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayPalGateway extends AbstractPaymentGateway
{
    public function code(): string { return 'paypal'; }
    public function name(): string { return 'PayPal'; }
    public function supports(): array { return ['refund', 'partial_refund', 'webhook']; }

    public function credentialSchema(): array
    {
        return [
            'client_id' => ['label' => 'Client ID', 'type' => 'text', 'required' => true],
            'client_secret' => ['label' => 'Client Secret', 'type' => 'password', 'required' => true],
            'webhook_id' => ['label' => 'Webhook ID', 'type' => 'text', 'required' => false, 'help' => 'From PayPal Developer → Webhooks.'],
        ];
    }

    public function testConnection(): array
    {
        try {
            $token = $this->fetchToken(true);
            return $token
                ? ['ok' => true, 'message' => 'Authenticated with PayPal.']
                : ['ok' => false, 'message' => 'PayPal auth returned no token.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function createCheckout(Order $order, Payment $payment): array
    {
        $token = $this->fetchToken();
        $res = Http::withToken($token)
            ->acceptJson()
            ->timeout(15)
            ->post($this->baseUrl() . '/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => $order->order_no,
                    'amount' => [
                        'currency_code' => strtoupper($this->config('currency', 'USD')),
                        'value' => number_format((float) $order->total, 2, '.', ''),
                    ],
                ]],
                'application_context' => [
                    'return_url' => $this->config('return_url', url('/order/' . $order->id . '/complete')),
                    'cancel_url' => $this->config('cancel_url', url('/checkout')),
                ],
            ]);

        if (! $res->successful()) {
            Log::error('PayPal order create failed', ['body' => $res->body()]);
            throw new \RuntimeException('PayPal rejected the order.');
        }
        $body = $res->json();
        $payment->update([
            'payment_id' => $body['id'] ?? null,
            'meta' => array_merge((array) $payment->meta, ['gateway_order' => $body]),
        ]);

        $approveUrl = collect($body['links'] ?? [])->firstWhere('rel', 'approve')['href'] ?? null;

        return [
            'gateway' => $this->code(),
            'gateway_order_id' => $body['id'] ?? null,
            'amount' => (int) round($order->total * 100),
            'currency' => strtoupper($this->config('currency', 'USD')),
            'sdk_config' => [
                'client_id' => $this->cred('client_id'),
                'order_id' => $body['id'] ?? null,
                'approve_url' => $approveUrl,
            ],
        ];
    }

    public function verifyCallback(Order $order, Payment $payment, array $callbackPayload): array
    {
        $orderId = $callbackPayload['orderID'] ?? $callbackPayload['token'] ?? $payment->payment_id;
        if (! $orderId) throw new \InvalidArgumentException('Missing PayPal order id.');

        $token = $this->fetchToken();
        $res = Http::withToken($token)
            ->acceptJson()
            ->timeout(15)
            ->post($this->baseUrl() . "/v2/checkout/orders/{$orderId}/capture", (object) []);
        if (! $res->successful()) {
            throw new \RuntimeException('PayPal capture failed: ' . $res->status());
        }
        $body = $res->json();
        if (($body['status'] ?? '') !== 'COMPLETED') {
            throw new \RuntimeException('PayPal order not completed: ' . ($body['status'] ?? 'unknown'));
        }
        return ['payment_id' => $body['id'] ?? $orderId, 'gateway_order_id' => $orderId, 'raw' => $body];
    }

    public function verifyWebhook(string $rawBody, array $headers): bool
    {
        $webhookId = $this->cred('webhook_id');
        if (empty($webhookId)) return false;
        try {
            $token = $this->fetchToken();
            $res = Http::withToken($token)
                ->acceptJson()
                ->timeout(10)
                ->post($this->baseUrl() . '/v1/notifications/verify-webhook-signature', [
                    'auth_algo' => $headers['paypal-auth-algo'][0] ?? '',
                    'cert_url' => $headers['paypal-cert-url'][0] ?? '',
                    'transmission_id' => $headers['paypal-transmission-id'][0] ?? '',
                    'transmission_sig' => $headers['paypal-transmission-sig'][0] ?? '',
                    'transmission_time' => $headers['paypal-transmission-time'][0] ?? '',
                    'webhook_id' => $webhookId,
                    'webhook_event' => json_decode($rawBody, true),
                ]);
            return $res->successful() && ($res->json('verification_status') === 'SUCCESS');
        } catch (\Throwable $e) {
            Log::warning('PayPal webhook verify exception: ' . $e->getMessage());
            return false;
        }
    }

    public function refund(Payment $payment, float $amount, ?string $reason = null): array
    {
        $captureId = $payment->meta['capture_id']
            ?? data_get($payment->meta, 'gateway_order.purchase_units.0.payments.captures.0.id');
        if (empty($captureId)) {
            throw new \RuntimeException('PayPal capture id not stored on payment record.');
        }
        $token = $this->fetchToken();
        $res = Http::withToken($token)
            ->acceptJson()
            ->timeout(15)
            ->post($this->baseUrl() . "/v2/payments/captures/{$captureId}/refund", [
                'amount' => [
                    'value' => number_format($amount, 2, '.', ''),
                    'currency_code' => strtoupper($this->config('currency', 'USD')),
                ],
                'note_to_payer' => $reason ?? 'Admin initiated refund',
            ]);
        if (! $res->successful()) {
            throw new \RuntimeException('PayPal refund failed: ' . $res->status());
        }
        return $res->json();
    }

    private function baseUrl(): string
    {
        return $this->isTestMode()
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
    }

    private function fetchToken(bool $forceRefresh = false): ?string
    {
        $key = 'paypal_token_' . $this->model->id . '_' . $this->model->mode;
        if ($forceRefresh) Cache::forget($key);
        return Cache::remember($key, 60 * 20, function () {
            $res = Http::withBasicAuth($this->cred('client_id'), $this->cred('client_secret'))
                ->asForm()
                ->timeout(10)
                ->post($this->baseUrl() . '/v1/oauth2/token', ['grant_type' => 'client_credentials']);
            if (! $res->successful()) return null;
            return $res->json('access_token');
        });
    }
}
