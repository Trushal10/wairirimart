<?php

namespace App\Services\Courier;

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * DTDC REST API. Uses api-key header authentication.
 */
class DtdcAdapter extends AbstractCourierAdapter
{
    public function code(): string { return 'dtdc'; }
    public function name(): string { return 'DTDC'; }
    public function supports(): array { return ['pickup', 'labels', 'tracking', 'cancel', 'cod']; }

    public function credentialSchema(): array
    {
        return [
            'api_key' => ['label' => 'API Key', 'type' => 'password', 'required' => true],
            'customer_code' => ['label' => 'Customer Code', 'type' => 'text', 'required' => true],
            'username' => ['label' => 'Tracking Username', 'type' => 'text', 'required' => false, 'help' => 'For tracking API only.'],
            'password' => ['label' => 'Tracking Password', 'type' => 'password', 'required' => false],
            'origin_pincode' => ['label' => 'Origin Pincode', 'type' => 'text', 'required' => true],
        ];
    }

    private function baseUrl(): string
    {
        return $this->isTestMode()
            ? 'https://demoapi.dtdc.com'
            : 'https://apis.dtdc.in';
    }

    private function headers(): array
    {
        return [
            'api-key' => $this->cred('api_key'),
            'Content-Type' => 'application/json',
        ];
    }

    public function testConnection(): array
    {
        if (empty($this->cred('api_key'))) return ['ok' => false, 'message' => 'Missing api_key.'];
        try {
            $res = Http::withHeaders($this->headers())->timeout(10)
                ->get($this->baseUrl() . '/dtdc-api/api/services/services');
            return $res->successful()
                ? ['ok' => true, 'message' => 'Authenticated with DTDC.']
                : ['ok' => false, 'message' => 'HTTP ' . $res->status()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function checkServiceability(string $pickupPincode, string $deliveryPincode, float $weight = 0.5, int $codAmount = 0): array
    {
        try {
            $res = Http::withHeaders($this->headers())->timeout(15)
                ->post($this->baseUrl() . '/dtdc-api/api/services/pincode/serviceability', [
                    'orgPincode' => $pickupPincode,
                    'desPincode' => $deliveryPincode,
                    'weight' => $weight,
                    'productCode' => $codAmount > 0 ? 'B2C-COD' : 'B2C-PPD',
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
            $res = Http::withHeaders($this->headers())->timeout(20)
                ->post($this->baseUrl() . '/dtdc-api/api/customer/integration/consignment/softdata', [
                    'consignments' => [[
                        'customer_code' => $this->cred('customer_code'),
                        'service_type_id' => $isCod ? 'B2C SMART EXPRESS' : 'B2C PRIORITY',
                        'load_type' => 'NON-DOCUMENT',
                        'description' => 'Order ' . $order->order_no,
                        'dimension_unit' => 'cm',
                        'length' => (float) $this->config('default_length', 10),
                        'width' => (float) $this->config('default_breadth', 10),
                        'height' => (float) $this->config('default_height', 5),
                        'weight_unit' => 'kg',
                        'weight' => (float) $this->config('default_weight', 0.5),
                        'declared_value' => (float) $order->total,
                        'num_pieces' => (int) $order->orderItems->sum('quantity'),
                        'origin_details' => [
                            'name' => $this->config('seller_name', config('app.name')),
                            'phone' => $this->config('seller_phone'),
                            'address_line_1' => $this->config('seller_address'),
                            'pincode' => $this->cred('origin_pincode'),
                            'city' => $this->config('seller_city'),
                            'state' => $this->config('seller_state'),
                        ],
                        'destination_details' => [
                            'name' => $order->shipping_name,
                            'phone' => $order->shipping_phone,
                            'email' => $order->shipping_email,
                            'address_line_1' => $order->shipping_address,
                            'pincode' => $order->shipping_pincode,
                            'city' => $order->shipping_city,
                            'state' => $order->shipping_state,
                        ],
                        'customer_reference_number' => $order->order_no,
                        'cod_collection_mode' => $isCod ? 'cash' : null,
                        'cod_amount' => $isCod ? (float) $order->total : 0,
                    ]],
                ]);
            return $this->handle($res, 'softdata');
        } catch (\Throwable $e) {
            return $this->error('createShipment', $e);
        }
    }

    public function assignAwb(Shipment $shipment, ?string $courierId = null): array
    {
        return ['message' => 'DTDC assigns AWB inline in shipment softdata response.'];
    }

    public function requestPickup(Shipment $shipment): array
    {
        try {
            $res = Http::withHeaders($this->headers())->timeout(15)
                ->post($this->baseUrl() . '/dtdc-api/api/customer/integration/pickup/register', [
                    'customer_code' => $this->cred('customer_code'),
                    'reference_number' => $shipment->awb_code,
                    'pickup_date' => now()->addDay()->format('Y-m-d'),
                    'no_of_shipments' => 1,
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
                ->get($this->baseUrl() . '/dtdc-api/api/consignment/label', [
                    'consignmentNumber' => $shipment->awb_code,
                ]);
            return $this->handle($res, 'label');
        } catch (\Throwable $e) {
            return $this->error('label', $e);
        }
    }

    public function generateInvoice(Shipment $shipment): array
    {
        return ['message' => 'DTDC does not expose an invoice API.'];
    }

    public function track(Shipment $shipment): array
    {
        try {
            $res = Http::asJson()->timeout(15)
                ->post($this->baseUrl() . '/dtdc-api/rest/JSONCnTrk/getTrackDetails', [
                    'trkType' => 'cnno',
                    'strcnno' => $shipment->awb_code,
                    'addtnlDtl' => 'Y',
                    'loginId' => $this->cred('username'),
                    'password' => $this->cred('password'),
                ]);
            return $this->handle($res, 'track');
        } catch (\Throwable $e) {
            return $this->error('track', $e);
        }
    }

    public function cancel(Shipment $shipment, ?string $reason = null): array
    {
        try {
            $res = Http::withHeaders($this->headers())->timeout(15)
                ->post($this->baseUrl() . '/dtdc-api/api/consignment/cancel', [
                    'consignmentNumber' => $shipment->awb_code,
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
            Log::warning('DTDC non-2xx', ['ctx' => $ctx, 'status' => $res->status(), 'body' => $res->body()]);
            return ['error' => true, 'status' => $res->status(), 'message' => $res->body()];
        }
        return $res->json() ?? [];
    }

    private function error(string $ctx, \Throwable $e): array
    {
        Log::error("DTDC {$ctx} exception: " . $e->getMessage());
        return ['error' => true, 'message' => $e->getMessage()];
    }
}
