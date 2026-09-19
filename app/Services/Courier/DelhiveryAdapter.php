<?php

namespace App\Services\Courier;

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Delhivery One (Prod: https://track.delhivery.com | Staging: https://staging-express.delhivery.com)
 * Uses a Bearer API token from the merchant panel.
 */
class DelhiveryAdapter extends AbstractCourierAdapter
{
    public function code(): string { return 'delhivery'; }
    public function name(): string { return 'Delhivery'; }
    public function supports(): array { return ['pickup', 'labels', 'tracking', 'cancel', 'serviceability', 'cod']; }

    public function credentialSchema(): array
    {
        return [
            'api_token' => ['label' => 'API Token', 'type' => 'password', 'required' => true, 'help' => 'From Delhivery Panel → Settings → API.'],
            'client_name' => ['label' => 'Client Name', 'type' => 'text', 'required' => true, 'help' => 'Registered client name — used in waybill create.'],
            'pickup_pincode' => ['label' => 'Pickup Pincode', 'type' => 'text', 'required' => true],
            'pickup_location_name' => ['label' => 'Pickup Location', 'type' => 'text', 'required' => true],
        ];
    }

    private function baseUrl(): string
    {
        return $this->isTestMode()
            ? 'https://staging-express.delhivery.com'
            : 'https://track.delhivery.com';
    }

    private function headers(): array
    {
        return [
            'Authorization' => 'Token ' . $this->cred('api_token'),
            'Accept' => 'application/json',
        ];
    }

    public function testConnection(): array
    {
        if (empty($this->cred('api_token'))) {
            return ['ok' => false, 'message' => 'Missing api_token.'];
        }
        try {
            $res = Http::withHeaders($this->headers())->timeout(10)
                ->get($this->baseUrl() . '/c/api/pin-codes/json/', ['filter_codes' => $this->cred('pickup_pincode', '110001')]);
            return $res->successful()
                ? ['ok' => true, 'message' => 'Authenticated with Delhivery.']
                : ['ok' => false, 'message' => 'HTTP ' . $res->status()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function checkServiceability(string $pickupPincode, string $deliveryPincode, float $weight = 0.5, int $codAmount = 0): array
    {
        try {
            $res = Http::withHeaders($this->headers())->timeout(15)
                ->get($this->baseUrl() . '/c/api/pin-codes/json/', ['filter_codes' => $deliveryPincode]);
            return $this->handle($res, 'pin-codes');
        } catch (\Throwable $e) {
            return $this->error('serviceability', $e);
        }
    }

    public function createShipment(Order $order): array
    {
        try {
            $payload = [
                'shipments' => [[
                    'name' => $order->shipping_name,
                    'add' => $order->shipping_address,
                    'city' => $order->shipping_city,
                    'state' => $order->shipping_state,
                    'country' => 'India',
                    'phone' => $order->shipping_phone,
                    'pin' => $order->shipping_pincode,
                    'order' => $order->order_no,
                    'payment_mode' => optional($order->payment)->type === 'cod' ? 'COD' : 'Prepaid',
                    'return_pin' => $this->cred('pickup_pincode'),
                    'return_city' => $this->config('return_city'),
                    'return_phone' => $this->config('return_phone'),
                    'return_add' => $this->config('return_address'),
                    'return_state' => $this->config('return_state'),
                    'return_country' => 'India',
                    'products_desc' => 'Order items',
                    'hsn_code' => '',
                    'cod_amount' => optional($order->payment)->type === 'cod' ? (float) $order->total : 0,
                    'order_date' => optional($order->created_at)->format('Y-m-d H:i') ?: now()->format('Y-m-d H:i'),
                    'total_amount' => (float) $order->total,
                    'seller_add' => $this->config('seller_address'),
                    'seller_name' => $this->config('seller_name', config('app.name')),
                    'seller_inv' => $order->order_no,
                    'quantity' => (int) $order->orderItems->sum('quantity'),
                    'waybill' => '',
                    'shipment_width' => (float) $this->config('default_breadth', 10),
                    'shipment_height' => (float) $this->config('default_height', 5),
                    'weight' => (float) $this->config('default_weight', 0.5) * 1000, // grams
                ]],
                'pickup_location' => [
                    'name' => $this->cred('pickup_location_name'),
                ],
            ];
            $res = Http::withHeaders(array_merge($this->headers(), ['Content-Type' => 'application/json']))
                ->timeout(20)
                ->send('POST', $this->baseUrl() . '/api/cmu/create.json', [
                    'body' => 'format=json&data=' . rawurlencode(json_encode($payload)),
                    'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                ]);
            return $this->handle($res, 'create.json');
        } catch (\Throwable $e) {
            return $this->error('createShipment', $e);
        }
    }

    public function assignAwb(Shipment $shipment, ?string $courierId = null): array
    {
        // Delhivery assigns waybill on create; no separate step needed.
        return ['message' => 'AWB is assigned automatically at shipment creation.'];
    }

    public function requestPickup(Shipment $shipment): array
    {
        try {
            $res = Http::withHeaders(array_merge($this->headers(), ['Content-Type' => 'application/json']))
                ->timeout(15)
                ->post($this->baseUrl() . '/fm/request/new/', [
                    'pickup_time' => now()->addDay()->format('H:i:s'),
                    'pickup_date' => now()->addDay()->format('Y-m-d'),
                    'pickup_location' => $this->cred('pickup_location_name'),
                    'expected_package_count' => 1,
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
                ->get($this->baseUrl() . '/api/p/packing_slip', [
                    'wbns' => $shipment->awb_code,
                    'pdf' => 'true',
                ]);
            $data = $this->handle($res, 'label');
            return $data;
        } catch (\Throwable $e) {
            return $this->error('label', $e);
        }
    }

    public function generateInvoice(Shipment $shipment): array
    {
        return ['message' => 'Delhivery does not expose invoice API — please issue invoice from your ERP.'];
    }

    public function track(Shipment $shipment): array
    {
        try {
            $res = Http::withHeaders($this->headers())->timeout(15)
                ->get($this->baseUrl() . '/api/v1/packages/json/', [
                    'waybill' => $shipment->awb_code,
                    'ref_ids' => $shipment->order?->order_no,
                ]);
            return $this->handle($res, 'track');
        } catch (\Throwable $e) {
            return $this->error('track', $e);
        }
    }

    public function cancel(Shipment $shipment, ?string $reason = null): array
    {
        try {
            $res = Http::withHeaders(array_merge($this->headers(), ['Content-Type' => 'application/json']))
                ->timeout(15)
                ->post($this->baseUrl() . '/api/p/edit', [
                    'waybill' => $shipment->awb_code,
                    'cancellation' => 'true',
                ]);
            return $this->handle($res, 'cancel');
        } catch (\Throwable $e) {
            return $this->error('cancel', $e);
        }
    }

    /* ---------------- helpers ---------------- */

    private function handle(\Illuminate\Http\Client\Response $res, string $ctx): array
    {
        if (! $res->successful()) {
            Log::warning('Delhivery non-2xx', ['ctx' => $ctx, 'status' => $res->status(), 'body' => $res->body()]);
            return ['error' => true, 'status' => $res->status(), 'message' => $res->json('rmk') ?? $res->body()];
        }
        return $res->json() ?? [];
    }

    private function error(string $ctx, \Throwable $e): array
    {
        Log::error("Delhivery {$ctx} exception: " . $e->getMessage());
        return ['error' => true, 'message' => $e->getMessage()];
    }
}
