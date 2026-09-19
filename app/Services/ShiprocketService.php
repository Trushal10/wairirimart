<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShiprocketService
{
    private const BASE = 'https://apiv2.shiprocket.in/v1/external';
    private const TOKEN_CACHE_KEY = 'shiprocket_token';
    private const TOKEN_TTL_SECONDS = 60 * 60 * 8; // Shiprocket tokens are typically valid ~10 days; keep 8h

    protected ?string $token = null;

    public function __construct()
    {
        $this->token = Cache::remember(self::TOKEN_CACHE_KEY, self::TOKEN_TTL_SECONDS, function () {
            return $this->fetchToken();
        });
    }

    /* ------------------------------------------------------------------ */
    /* Auth                                                                */
    /* ------------------------------------------------------------------ */

    private function fetchToken(): ?string
    {
        try {
            $response = Http::asJson()->timeout(15)->post(self::BASE . '/auth/login', [
                'email' => config('services.shiprocket.email'),
                'password' => config('services.shiprocket.password'),
            ]);
            if (! $response->successful()) {
                Log::error('Shiprocket auth failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }
            return $response->json('token');
        } catch (\Throwable $e) {
            Log::error('Shiprocket auth exception: ' . $e->getMessage());
            return null;
        }
    }

    public function refreshToken(): ?string
    {
        Cache::forget(self::TOKEN_CACHE_KEY);
        $this->token = Cache::remember(self::TOKEN_CACHE_KEY, self::TOKEN_TTL_SECONDS, fn () => $this->fetchToken());
        return $this->token;
    }

    private function api(): PendingRequest
    {
        // asJson() must precede the request — without it, ->post($url, $body)
        // sends application/x-www-form-urlencoded, which Shiprocket's stricter
        // endpoints (courier/assign/awb) reject with "Required field missing".
        return Http::withToken((string) $this->token)
            ->asJson()
            ->acceptJson()
            ->timeout(20);
    }

    /* ------------------------------------------------------------------ */
    /* Serviceability                                                      */
    /* ------------------------------------------------------------------ */

    public function checkServiceability(string $pickupPincode, string $deliveryPincode, int|float $weightKg = 0.5, int $codAmount = 0): array
    {
        if (empty($this->token)) {
            return ['error' => true, 'message' => 'Shiprocket authentication failed.'];
        }
        try {
            $response = $this->api()->get(self::BASE . '/courier/serviceability/', [
                'pickup_postcode' => $pickupPincode,
                'delivery_postcode' => $deliveryPincode,
                'weight' => $weightKg,
                'cod' => $codAmount > 0 ? 1 : 0,
            ]);
            return $this->handle($response, 'serviceability');
        } catch (\Throwable $e) {
            return $this->error('serviceability', $e);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Create Order                                                        */
    /* ------------------------------------------------------------------ */

    public function createOrder($order, array $items = []): array
    {
        if (empty($this->token)) {
            return ['error' => true, 'message' => 'Shiprocket authentication failed.'];
        }

        $orderItems = ! empty($items) ? $items : $this->buildItemsFromOrder($order);
        $payload = [
            'order_id' => $order->order_no,
            'order_date' => optional($order->created_at)->format('Y-m-d H:i') ?: now()->format('Y-m-d H:i'),
            'pickup_location' => config('services.shiprocket.pickup_location', 'Primary'),
            'channel_id' => config('services.shiprocket.channel_id') ?: null,
            'comment' => 'Auto-created via API',
            'billing_customer_name' => $order->shipping_name,
            'billing_last_name' => '',
            'billing_address' => $order->shipping_address,
            'billing_city' => $order->shipping_city,
            'billing_pincode' => $order->shipping_pincode,
            'billing_state' => $order->shipping_state,
            'billing_country' => 'India',
            'billing_email' => $order->shipping_email,
            'billing_phone' => $order->shipping_phone,
            'shipping_is_billing' => true,
            'order_items' => $orderItems,
            'payment_method' => optional($order->payment)->type === 'cod' ? 'COD' : 'Prepaid',
            'sub_total' => (float) $order->sub_total,
            'length' => (float) config('services.shiprocket.default_length', 10),
            'breadth' => (float) config('services.shiprocket.default_breadth', 10),
            'height' => (float) config('services.shiprocket.default_height', 5),
            'weight' => (float) config('services.shiprocket.default_weight', 0.5),
        ];

        try {
            $response = $this->api()->post(self::BASE . '/orders/create/adhoc', $payload);
            return $this->handle($response, 'orders/create/adhoc');
        } catch (\Throwable $e) {
            return $this->error('createOrder', $e);
        }
    }

    /**
     * Wrapper the admin panel calls when assigning a partner.
     * Returns a normalized shape.
     */
    public function createShipment(?string $partnerCode, $order, array $items = []): array
    {
        $result = $this->createOrder($order, $items);
        if (! empty($result['error'])) {
            return $result;
        }
        return [
            'provider_order_id' => $result['order_id'] ?? null,
            'provider_shipment_id' => $result['shipment_id'] ?? null,
            'tracking_number' => $result['awb_code'] ?? null,
            'label_url' => $result['label_url'] ?? null,
            'courier_name' => $result['courier_name'] ?? null,
            'raw' => $result,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* AWB / courier assignment                                            */
    /* ------------------------------------------------------------------ */

    public function assignAwb(string|int $shipmentId, ?string $courierId = null): array
    {
        if (empty($this->token)) {
            return ['error' => true, 'message' => 'Shiprocket authentication failed.'];
        }
        // Shiprocket requires a POSITIVE integer shipment_id.
        $shipmentId = (int) $shipmentId;
        if ($shipmentId <= 0) {
            return [
                'error' => true,
                'message' => 'assign_awb: shipment_id is required and must be a positive integer '
                    . '(the numeric shipment_id returned from /orders/create/adhoc).',
            ];
        }
        try {
            $body = ['shipment_id' => $shipmentId];
            if ($courierId !== null && $courierId !== '') {
                $body['courier_id'] = (int) $courierId;
            }
            $response = $this->api()->post(self::BASE . '/courier/assign/awb', $body);
            return $this->handle($response, 'courier/assign/awb');
        } catch (\Throwable $e) {
            return $this->error('assignAwb', $e);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Pickup, label, invoice, manifest                                    */
    /* ------------------------------------------------------------------ */

    public function requestPickup(array $shipmentIds): array
    {
        return $this->safe('courier/generate/pickup', ['shipment_id' => $shipmentIds]);
    }

    public function generateLabel(array $shipmentIds): array
    {
        return $this->safe('courier/generate/label', ['shipment_id' => $shipmentIds]);
    }

    public function generateInvoice(array $orderIds): array
    {
        return $this->safe('orders/print/invoice', ['ids' => $orderIds]);
    }

    public function generateManifest(array $shipmentIds): array
    {
        return $this->safe('manifests/generate', ['shipment_id' => $shipmentIds]);
    }

    /* ------------------------------------------------------------------ */
    /* Tracking                                                            */
    /* ------------------------------------------------------------------ */

    public function trackByAwb(string $awb): array
    {
        if (empty($this->token)) {
            return ['error' => true, 'message' => 'Shiprocket authentication failed.'];
        }
        try {
            $response = $this->api()->get(self::BASE . "/courier/track/awb/{$awb}");
            return $this->handle($response, "track/awb/{$awb}");
        } catch (\Throwable $e) {
            return $this->error('trackByAwb', $e);
        }
    }

    public function trackByShipmentId(string|int $shipmentId): array
    {
        if (empty($this->token)) {
            return ['error' => true, 'message' => 'Shiprocket authentication failed.'];
        }
        try {
            $response = $this->api()->get(self::BASE . "/courier/track/shipment/{$shipmentId}");
            return $this->handle($response, "track/shipment/{$shipmentId}");
        } catch (\Throwable $e) {
            return $this->error('trackByShipmentId', $e);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Cancel                                                              */
    /* ------------------------------------------------------------------ */

    public function cancelOrders(array $orderIds): array
    {
        return $this->safe('orders/cancel', ['ids' => $orderIds]);
    }

    public function cancelShipments(array $awbs): array
    {
        return $this->safe('orders/cancel/shipment/awbs', ['awbs' => $awbs]);
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    private function safe(string $path, array $body): array
    {
        if (empty($this->token)) {
            return ['error' => true, 'message' => 'Shiprocket authentication failed.'];
        }
        try {
            $response = $this->api()->post(self::BASE . '/' . ltrim($path, '/'), $body);
            return $this->handle($response, $path);
        } catch (\Throwable $e) {
            return $this->error($path, $e);
        }
    }

    private function handle(\Illuminate\Http\Client\Response $response, string $context): array
    {
        if ($response->status() === 401) {
            $this->refreshToken();
            return ['error' => true, 'message' => 'Shiprocket auth expired; retry the operation.'];
        }
        if (! $response->successful()) {
            Log::warning('Shiprocket API non-2xx', [
                'context' => $context,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return [
                'error' => true,
                'message' => $response->json('message') ?? 'Shiprocket API error.',
                'status' => $response->status(),
                'body' => $response->json(),
            ];
        }
        return $response->json() ?? [];
    }

    private function error(string $context, \Throwable $e): array
    {
        Log::error("Shiprocket {$context} exception: " . $e->getMessage());
        return ['error' => true, 'message' => $e->getMessage()];
    }

    private function buildItemsFromOrder($order): array
    {
        $items = [];
        if (empty($order->orderItems)) {
            return $items;
        }
        foreach ($order->orderItems as $item) {
            $items[] = [
                'name' => optional($item->product)->name ?? ('Product #' . $item->product_id),
                'sku' => optional($item->product)->sku ?? (string) $item->product_id,
                'units' => (int) $item->quantity,
                'selling_price' => (float) $item->price,
                'discount' => 0,
                'tax' => 0,
                'hsn' => 0,
            ];
        }
        return $items;
    }
}
