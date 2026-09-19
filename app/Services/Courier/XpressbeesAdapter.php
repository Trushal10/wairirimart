<?php

namespace App\Services\Courier;

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Xpressbees API — email/password login → JWT token → Bearer for all calls.
 */
class XpressbeesAdapter extends AbstractCourierAdapter
{
    public function code(): string { return 'xpressbees'; }
    public function name(): string { return 'Xpressbees'; }
    public function supports(): array { return ['pickup', 'labels', 'tracking', 'cancel', 'cod', 'serviceability']; }

    public function credentialSchema(): array
    {
        return [
            'email' => ['label' => 'Email', 'type' => 'text', 'required' => true],
            'password' => ['label' => 'Password', 'type' => 'password', 'required' => true],
            'pickup_pincode' => ['label' => 'Pickup Pincode', 'type' => 'text', 'required' => true],
            'business_name' => ['label' => 'Business Name', 'type' => 'text', 'required' => true],
        ];
    }

    private function baseUrl(): string
    {
        return $this->isTestMode()
            ? 'https://shipment.xpressbees.com/api'
            : 'https://shipment.xpressbees.com/api';
    }

    private function getToken(bool $refresh = false): ?string
    {
        $key = 'xpressbees_token_' . $this->model->id;
        if ($refresh) Cache::forget($key);
        return Cache::remember($key, 60 * 60 * 6, function () {
            $res = Http::asJson()->timeout(15)
                ->post($this->baseUrl() . '/users/login', [
                    'email' => $this->cred('email'),
                    'password' => $this->cred('password'),
                ]);
            if (! $res->successful()) return null;
            return $res->json('data');
        });
    }

    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . ($this->getToken() ?? ''),
            'Content-Type' => 'application/json',
        ];
    }

    public function testConnection(): array
    {
        try {
            $t = $this->getToken(true);
            return $t
                ? ['ok' => true, 'message' => 'Authenticated with Xpressbees.']
                : ['ok' => false, 'message' => 'Xpressbees auth returned no token.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function checkServiceability(string $pickupPincode, string $deliveryPincode, float $weight = 0.5, int $codAmount = 0): array
    {
        try {
            $res = Http::withHeaders($this->headers())->timeout(15)
                ->post($this->baseUrl() . '/courier/serviceability', [
                    'origin' => $pickupPincode,
                    'destination' => $deliveryPincode,
                    'payment_type' => $codAmount > 0 ? 'cod' : 'prepaid',
                    'weight' => (int) ($weight * 1000),
                ]);
            return $this->handle($res, 'serviceability');
        } catch (\Throwable $e) {
            return $this->error('serviceability', $e);
        }
    }

    public function createShipment(Order $order): array
    {
        try {
            $isCod = optional($order->payment)->type === 'cod';
            $orderItems = $order->orderItems->map(fn ($i) => [
                'name' => optional($i->product)->name ?? ('Product #' . $i->product_id),
                'qty' => (int) $i->quantity,
                'price' => (float) $i->price,
                'sku' => optional($i->product)->sku ?? (string) $i->product_id,
            ])->all();

            $res = Http::withHeaders($this->headers())->timeout(20)
                ->post($this->baseUrl() . '/shipments2', [
                    'order_number' => $order->order_no,
                    'payment_type' => $isCod ? 'cod' : 'prepaid',
                    'package_weight' => (int) ($this->config('default_weight', 0.5) * 1000),
                    'package_length' => (int) $this->config('default_length', 10),
                    'package_breadth' => (int) $this->config('default_breadth', 10),
                    'package_height' => (int) $this->config('default_height', 5),
                    'request_auto_pickup' => 'yes',
                    'consignee' => [
                        'name' => $order->shipping_name,
                        'address' => $order->shipping_address,
                        'address_2' => '',
                        'city' => $order->shipping_city,
                        'state' => $order->shipping_state,
                        'pincode' => $order->shipping_pincode,
                        'phone' => $order->shipping_phone,
                    ],
                    'pickup' => [
                        'warehouse_name' => $this->cred('business_name'),
                        'name' => $this->cred('business_name'),
                        'address' => $this->config('seller_address'),
                        'city' => $this->config('seller_city'),
                        'state' => $this->config('seller_state'),
                        'pincode' => $this->cred('pickup_pincode'),
                        'phone' => $this->config('seller_phone'),
                    ],
                    'order_items' => $orderItems,
                    'collectable_amount' => $isCod ? (float) $order->total : 0,
                    'total_order_value' => (float) $order->total,
                ]);
            return $this->handle($res, 'create_shipment');
        } catch (\Throwable $e) {
            return $this->error('createShipment', $e);
        }
    }

    public function assignAwb(Shipment $shipment, ?string $courierId = null): array
    {
        return ['message' => 'Xpressbees returns AWB inline in shipment creation response.'];
    }

    public function requestPickup(Shipment $shipment): array
    {
        try {
            $res = Http::withHeaders($this->headers())->timeout(15)
                ->post($this->baseUrl() . '/shipments/pickup', [
                    'awb' => $shipment->awb_code,
                ]);
            return $this->handle($res, 'pickup');
        } catch (\Throwable $e) {
            return $this->error('pickup', $e);
        }
    }

    public function generateLabel(Shipment $shipment): array
    {
        try {
            $res = Http::withHeaders($this->headers())->timeout(15)
                ->post($this->baseUrl() . '/shipments/label', [
                    'awb' => [$shipment->awb_code],
                ]);
            return $this->handle($res, 'label');
        } catch (\Throwable $e) {
            return $this->error('label', $e);
        }
    }

    public function generateInvoice(Shipment $shipment): array
    {
        return ['message' => 'Xpressbees does not expose an invoice API.'];
    }

    public function track(Shipment $shipment): array
    {
        try {
            $res = Http::withHeaders($this->headers())->timeout(15)
                ->get($this->baseUrl() . '/shipments2/track/' . $shipment->awb_code);
            return $this->handle($res, 'track');
        } catch (\Throwable $e) {
            return $this->error('track', $e);
        }
    }

    public function cancel(Shipment $shipment, ?string $reason = null): array
    {
        try {
            $res = Http::withHeaders($this->headers())->timeout(15)
                ->post($this->baseUrl() . '/shipments/cancel', [
                    'awb' => $shipment->awb_code,
                    'reason' => $reason ?? 'Admin cancellation',
                ]);
            return $this->handle($res, 'cancel');
        } catch (\Throwable $e) {
            return $this->error('cancel', $e);
        }
    }

    private function handle(\Illuminate\Http\Client\Response $res, string $ctx): array
    {
        if (! $res->successful()) {
            Log::warning('Xpressbees non-2xx', ['ctx' => $ctx, 'status' => $res->status(), 'body' => $res->body()]);
            return ['error' => true, 'status' => $res->status(), 'message' => $res->body()];
        }
        return $res->json() ?? [];
    }

    private function error(string $ctx, \Throwable $e): array
    {
        Log::error("Xpressbees {$ctx} exception: " . $e->getMessage());
        return ['error' => true, 'message' => $e->getMessage()];
    }
}
