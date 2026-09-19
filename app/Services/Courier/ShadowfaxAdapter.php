<?php

namespace App\Services\Courier;

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Shadowfax courier adapter.
 *
 * Auth flow: POST client credentials → JWT bearer token (cached ~4h).
 * Endpoints below match Shadowfax's aggregator API surface. Some seller
 * accounts use slightly different paths — override `api_base_url` on the
 * DeliveryPartner row if your onboarding pack points somewhere else.
 */
class ShadowfaxAdapter extends AbstractCourierAdapter
{
    private const PROD_BASE = 'https://api.shadowfax.in';
    private const STAGE_BASE = 'https://staging-api.shadowfax.in';

    public function code(): string { return 'shadowfax'; }
    public function name(): string { return 'Shadowfax'; }

    public function supports(): array
    {
        return ['pickup', 'labels', 'tracking', 'cancel', 'cod', 'serviceability'];
    }

    public function credentialSchema(): array
    {
        return [
            'client_id' => [
                'label' => 'Client ID',
                'type' => 'text',
                'required' => true,
                'help' => 'Provided by Shadowfax onboarding — sometimes called "seller code".',
            ],
            'client_secret' => [
                'label' => 'Client Secret',
                'type' => 'password',
                'required' => true,
            ],
            'seller_id' => [
                'label' => 'Seller ID',
                'type' => 'text',
                'required' => true,
                'help' => 'Your Shadowfax seller/merchant identifier.',
            ],
            'pickup_pincode' => [
                'label' => 'Pickup Pincode',
                'type' => 'text',
                'required' => true,
            ],
            'pickup_location_code' => [
                'label' => 'Pickup Location Code',
                'type' => 'text',
                'required' => true,
                'help' => 'Registered pickup location code (e.g. "BLR01").',
            ],
            'webhook_token' => [
                'label' => 'Webhook Token',
                'type' => 'password',
                'required' => false,
                'help' => 'Shared secret used to authenticate status-update webhooks.',
            ],
        ];
    }

    /* ------------------------ Auth ------------------------ */

    private function baseUrl(): string
    {
        return $this->model->api_base_url ?: ($this->isTestMode() ? self::STAGE_BASE : self::PROD_BASE);
    }

    private function getToken(bool $forceRefresh = false): ?string
    {
        $key = 'shadowfax_token_' . $this->model->id;
        if ($forceRefresh) Cache::forget($key);

        return Cache::remember($key, 60 * 60 * 4, function () {
            try {
                $res = Http::asJson()->acceptJson()->timeout(15)
                    ->post($this->baseUrl() . '/api/authenticate/', [
                        'client_id' => $this->cred('client_id'),
                        'client_secret' => $this->cred('client_secret'),
                    ]);
                if (! $res->successful()) {
                    Log::error('Shadowfax auth failed', [
                        'status' => $res->status(),
                        'body' => $res->body(),
                    ]);
                    return null;
                }
                return $res->json('access_token') ?: $res->json('token');
            } catch (\Throwable $e) {
                Log::error('Shadowfax auth exception: ' . $e->getMessage());
                return null;
            }
        });
    }

    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . ((string) $this->getToken()),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    public function testConnection(): array
    {
        if (empty($this->cred('client_id')) || empty($this->cred('client_secret'))) {
            return ['ok' => false, 'message' => 'Client ID / secret missing.'];
        }
        try {
            $token = $this->getToken(true);
            return $token
                ? ['ok' => true, 'message' => 'Authenticated with Shadowfax.']
                : ['ok' => false, 'message' => 'Shadowfax auth returned no token.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /* --------------------- Serviceability --------------------- */

    public function checkServiceability(string $pickupPincode, string $deliveryPincode, float $weight = 0.5, int $codAmount = 0): array
    {
        try {
            $res = Http::withHeaders($this->headers())->timeout(15)
                ->post($this->baseUrl() . '/api/v2/serviceability/', [
                    'seller_id' => $this->cred('seller_id'),
                    'source_pincode' => $pickupPincode,
                    'destination_pincode' => $deliveryPincode,
                    'weight_gms' => (int) ($weight * 1000),
                    'payment_type' => $codAmount > 0 ? 'COD' : 'PPD',
                ]);
            return $this->handle($res, 'serviceability');
        } catch (\Throwable $e) {
            return $this->error('serviceability', $e);
        }
    }

    /* --------------------- Create Shipment --------------------- */

    public function createShipment(Order $order): array
    {
        try {
            $isCod = optional($order->payment)->type === 'cod';

            $items = $order->orderItems->map(fn ($i) => [
                'sku' => optional($i->product)->sku ?? (string) $i->product_id,
                'name' => optional($i->product)->name ?? ('Item #' . $i->product_id),
                'qty' => (int) $i->quantity,
                'price' => (float) $i->price,
                'category' => 'General',
            ])->all();

            $payload = [
                'seller_id' => $this->cred('seller_id'),
                'orders' => [[
                    'order_id' => $order->order_no,
                    'order_date' => optional($order->created_at)->format('Y-m-d H:i:s') ?: now()->format('Y-m-d H:i:s'),
                    'invoice_number' => $order->order_no,
                    'invoice_amount' => (float) $order->total,
                    'payment_type' => $isCod ? 'COD' : 'PPD',
                    'cod_amount' => $isCod ? (float) $order->total : 0,
                    'total_amount' => (float) $order->total,
                    'weight_gms' => (int) ($this->config('default_weight', 0.5) * 1000),
                    'length_cm' => (float) $this->config('default_length', 10),
                    'breadth_cm' => (float) $this->config('default_breadth', 10),
                    'height_cm' => (float) $this->config('default_height', 5),
                    'pickup' => [
                        'code' => $this->cred('pickup_location_code'),
                        'pincode' => $this->cred('pickup_pincode'),
                    ],
                    'consignee' => [
                        'name' => $order->shipping_name,
                        'phone' => $order->shipping_phone,
                        'email' => $order->shipping_email,
                        'address_line_1' => $order->shipping_address,
                        'city' => $order->shipping_city,
                        'state' => $order->shipping_state,
                        'pincode' => $order->shipping_pincode,
                        'country' => 'India',
                    ],
                    'items' => $items,
                ]],
            ];

            $res = Http::withHeaders($this->headers())->timeout(20)
                ->post($this->baseUrl() . '/api/v2/order/create/', $payload);

            $result = $this->handle($res, 'order/create');
            if (! empty($result['error'])) return $result;

            // Normalise fields so ShipmentService can pick them up regardless of nesting.
            $order0 = $result['orders'][0] ?? ($result['data'][0] ?? $result);
            return array_merge($result, [
                'order_id' => $order0['sf_order_id'] ?? $order0['order_id'] ?? null,
                'shipment_id' => $order0['shipment_id'] ?? $order0['sf_shipment_id'] ?? null,
                'awb_code' => $order0['awb'] ?? $order0['awb_code'] ?? null,
                'courier_name' => $order0['courier_name'] ?? 'Shadowfax',
                'label_url' => $order0['label_url'] ?? $order0['pdf_link'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return $this->error('createShipment', $e);
        }
    }

    /* ------------------ AWB / Pickup / Label ------------------ */

    public function assignAwb(Shipment $shipment, ?string $courierId = null): array
    {
        // Shadowfax assigns AWB at shipment creation; the endpoint below is a
        // safety-net for the rare case where AWB comes back on a second call.
        try {
            $res = Http::withHeaders($this->headers())->timeout(15)
                ->post($this->baseUrl() . '/api/v2/order/awb/', [
                    'seller_id' => $this->cred('seller_id'),
                    'order_id' => $shipment->provider_order_id ?: optional($shipment->order)->order_no,
                ]);
            $result = $this->handle($res, 'order/awb');
            if (! empty($result['error'])) return $result;
            return array_merge($result, [
                'awb_code' => $result['awb'] ?? $result['awb_code'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return $this->error('assignAwb', $e);
        }
    }

    public function requestPickup(Shipment $shipment): array
    {
        try {
            $res = Http::withHeaders($this->headers())->timeout(15)
                ->post($this->baseUrl() . '/api/v2/pickup/schedule/', [
                    'seller_id' => $this->cred('seller_id'),
                    'pickup_location_code' => $this->cred('pickup_location_code'),
                    'awbs' => array_filter([$shipment->awb_code]),
                    'expected_pickup_date' => now()->addDay()->format('Y-m-d'),
                ]);
            return $this->handle($res, 'pickup/schedule');
        } catch (\Throwable $e) {
            return $this->error('pickup', $e);
        }
    }

    public function generateLabel(Shipment $shipment): array
    {
        if (empty($shipment->awb_code)) {
            return ['error' => true, 'message' => 'AWB not yet assigned.'];
        }
        try {
            $res = Http::withHeaders($this->headers())->timeout(15)
                ->post($this->baseUrl() . '/api/v2/label/', [
                    'seller_id' => $this->cred('seller_id'),
                    'awbs' => [$shipment->awb_code],
                ]);
            $result = $this->handle($res, 'label');
            if (! empty($result['error'])) return $result;

            // Shadowfax returns a signed S3 URL that expires — surface it in
            // the canonical `label_url` key so ShipmentService persists it.
            $url = $result['label_url']
                ?? $result['pdf_link']
                ?? ($result['data'][0]['label_url'] ?? null)
                ?? ($result['labels'][0]['url'] ?? null);
            return array_merge($result, ['label_url' => $url]);
        } catch (\Throwable $e) {
            return $this->error('label', $e);
        }
    }

    public function generateInvoice(Shipment $shipment): array
    {
        return ['message' => 'Shadowfax does not expose an invoice API — please issue invoices from your ERP.'];
    }

    /* ------------------------ Tracking ------------------------ */

    public function track(Shipment $shipment): array
    {
        if (empty($shipment->awb_code)) {
            return ['error' => true, 'message' => 'AWB not yet assigned.'];
        }
        try {
            $res = Http::withHeaders($this->headers())->timeout(15)
                ->get($this->baseUrl() . '/api/v2/tracking/' . urlencode($shipment->awb_code) . '/');
            return $this->handle($res, 'tracking');
        } catch (\Throwable $e) {
            return $this->error('tracking', $e);
        }
    }

    public function cancel(Shipment $shipment, ?string $reason = null): array
    {
        try {
            $res = Http::withHeaders($this->headers())->timeout(15)
                ->post($this->baseUrl() . '/api/v2/order/cancel/', [
                    'seller_id' => $this->cred('seller_id'),
                    'awbs' => array_filter([$shipment->awb_code]),
                    'reason' => $reason ?? 'Admin cancellation',
                ]);
            return $this->handle($res, 'cancel');
        } catch (\Throwable $e) {
            return $this->error('cancel', $e);
        }
    }

    /* ---------------------- Status mapping ---------------------- */

    public function normalizeStatus(?string $providerStatus): ?string
    {
        if (empty($providerStatus)) return null;
        $s = strtolower(trim($providerStatus));
        return match (true) {
            str_contains($s, 'delivered') => Shipment::STATUS_DELIVERED,
            str_contains($s, 'rto') || str_contains($s, 'returned to origin') => Shipment::STATUS_RTO,
            str_contains($s, 'cancel') => Shipment::STATUS_CANCELLED,
            str_contains($s, 'out for delivery') || $s === 'ofd' => Shipment::STATUS_OUT_FOR_DELIVERY,
            str_contains($s, 'in transit') || str_contains($s, 'dispatched') || str_contains($s, 'shipped') => Shipment::STATUS_IN_TRANSIT,
            str_contains($s, 'picked up') || str_contains($s, 'pickup complete') || str_contains($s, 'pickup done') => Shipment::STATUS_PICKED_UP,
            str_contains($s, 'pickup scheduled') || str_contains($s, 'pickup pending') => Shipment::STATUS_PICKUP_SCHEDULED,
            str_contains($s, 'awb') && str_contains($s, 'assigned') => Shipment::STATUS_AWB_ASSIGNED,
            default => parent::normalizeStatus($providerStatus),
        };
    }

    /* ------------------------ Helpers ------------------------ */

    private function handle(\Illuminate\Http\Client\Response $res, string $ctx): array
    {
        if ($res->status() === 401) {
            // Token likely expired — bust cache so the next call re-auths.
            Cache::forget('shadowfax_token_' . $this->model->id);
            return ['error' => true, 'message' => 'Shadowfax auth expired; please retry.'];
        }
        if (! $res->successful()) {
            Log::warning('Shadowfax non-2xx', [
                'ctx' => $ctx,
                'status' => $res->status(),
                'body' => $res->body(),
            ]);
            return [
                'error' => true,
                'status' => $res->status(),
                'message' => $res->json('message') ?? $res->json('error') ?? 'Shadowfax API error.',
                'body' => $res->json(),
            ];
        }
        return $res->json() ?? [];
    }

    private function error(string $ctx, \Throwable $e): array
    {
        Log::error("Shadowfax {$ctx} exception: " . $e->getMessage());
        return ['error' => true, 'message' => $e->getMessage()];
    }
}
