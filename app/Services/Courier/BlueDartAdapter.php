<?php

namespace App\Services\Courier;

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Blue Dart REST API. Uses their MyDart API-generated login+licence_key pair
 * and a JWT-token flow. Test URL differs from prod.
 */
class BlueDartAdapter extends AbstractCourierAdapter
{
    public function code(): string { return 'bluedart'; }
    public function name(): string { return 'Blue Dart'; }
    public function supports(): array { return ['pickup', 'labels', 'tracking', 'cancel', 'cod']; }

    public function credentialSchema(): array
    {
        return [
            'login_id' => ['label' => 'Login ID', 'type' => 'text', 'required' => true],
            'licence_key' => ['label' => 'Licence Key', 'type' => 'password', 'required' => true],
            'customer_code' => ['label' => 'Customer Code', 'type' => 'text', 'required' => true],
            'origin_area' => ['label' => 'Origin Area Code', 'type' => 'text', 'required' => true],
            'api_key' => ['label' => 'API Key (subscription)', 'type' => 'password', 'required' => true, 'help' => 'Ocp-Apim-Subscription-Key from Blue Dart API portal.'],
        ];
    }

    private function baseUrl(): string
    {
        return $this->isTestMode()
            ? 'https://apigateway-sandbox.bluedart.com'
            : 'https://apigateway.bluedart.com';
    }

    public function testConnection(): array
    {
        try {
            $token = $this->getToken(true);
            return $token
                ? ['ok' => true, 'message' => 'Authenticated with Blue Dart.']
                : ['ok' => false, 'message' => 'Blue Dart auth returned no token.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function getToken(bool $refresh = false): ?string
    {
        $key = 'bluedart_token_' . $this->model->id;
        if ($refresh) Cache::forget($key);
        return Cache::remember($key, 60 * 60 * 5, function () {
            $res = Http::withHeaders([
                'ClientID' => $this->cred('login_id'),
                'api_key' => $this->cred('licence_key'),
                'Ocp-Apim-Subscription-Key' => $this->cred('api_key'),
            ])->acceptJson()->timeout(15)
                ->get($this->baseUrl() . '/in/transportation/token/v1/login');
            return $res->successful() ? $res->json('JWTToken') : null;
        });
    }

    private function headers(): array
    {
        return [
            'JWTToken' => (string) $this->getToken(),
            'Ocp-Apim-Subscription-Key' => $this->cred('api_key'),
            'Content-Type' => 'application/json',
        ];
    }

    public function checkServiceability(string $pickupPincode, string $deliveryPincode, float $weight = 0.5, int $codAmount = 0): array
    {
        try {
            $res = Http::withHeaders($this->headers())->timeout(15)
                ->post($this->baseUrl() . '/in/transportation/finder/v1/ServiceFinderNew', [
                    'pPinCodeFrom' => $pickupPincode,
                    'pPinCodeTo' => $deliveryPincode,
                    'pProductCode' => $codAmount > 0 ? 'C' : 'D',
                    'pCustomerCode' => $this->cred('customer_code'),
                    'pWeight' => $weight,
                ]);
            return $this->handle($res, 'servicefinder');
        } catch (\Throwable $e) {
            return $this->error('serviceability', $e);
        }
    }

    public function createShipment(Order $order): array
    {
        try {
            $isCod = optional($order->payment)->type === 'cod';
            $payload = [
                'Request' => [
                    'Consignee' => [
                        'ConsigneeName' => $order->shipping_name,
                        'ConsigneeAddress1' => $order->shipping_address,
                        'ConsigneeAttention' => $order->shipping_name,
                        'ConsigneePincode' => $order->shipping_pincode,
                        'ConsigneeTelephone' => $order->shipping_phone,
                        'ConsigneeEmailID' => $order->shipping_email,
                    ],
                    'Services' => [
                        'ProductCode' => $isCod ? 'A' : 'D',
                        'ProductType' => 'Dutiables',
                        'PieceCount' => (int) $order->orderItems->sum('quantity'),
                        'ActualWeight' => (float) $this->config('default_weight', 0.5),
                        'CreditReferenceNo' => $order->order_no,
                        'OriginArea' => $this->cred('origin_area'),
                        'DestinationArea' => $order->shipping_city,
                        'PickupDate' => now()->addDay()->format('Y-m-d'),
                        'PickupTime' => '1600',
                        'DeliveryTimeSlot' => 'ANY',
                        'CollectableAmount' => $isCod ? (float) $order->total : 0,
                        'DeclaredValue' => (float) $order->total,
                    ],
                    'Shipper' => [
                        'CustomerCode' => $this->cred('customer_code'),
                        'OriginArea' => $this->cred('origin_area'),
                    ],
                ],
                'Profile' => [
                    'LoginID' => $this->cred('login_id'),
                    'LicenceKey' => $this->cred('licence_key'),
                    'Api_type' => 'S',
                ],
            ];
            $res = Http::withHeaders($this->headers())->timeout(20)
                ->post($this->baseUrl() . '/in/transportation/waybill/v1/GenerateWayBill', $payload);
            return $this->handle($res, 'waybill.create');
        } catch (\Throwable $e) {
            return $this->error('createShipment', $e);
        }
    }

    public function assignAwb(Shipment $shipment, ?string $courierId = null): array
    {
        return ['message' => 'Blue Dart returns AWB inline from waybill create.'];
    }

    public function requestPickup(Shipment $shipment): array
    {
        try {
            $res = Http::withHeaders($this->headers())->timeout(15)
                ->post($this->baseUrl() . '/in/transportation/pickup/v1/PickupRegistration', [
                    'Request' => [
                        'CustomerCode' => $this->cred('customer_code'),
                        'CustomerName' => $this->config('seller_name', config('app.name')),
                        'AreaCode' => $this->cred('origin_area'),
                        'PickupDate' => now()->addDay()->format('Y-m-d'),
                        'PickupTime' => '1600',
                        'ShipmentPickupTime' => '1600',
                        'AWBNo' => [$shipment->awb_code],
                        'NumberofPieces' => 1,
                        'WeightofShipment' => (float) $this->config('default_weight', 0.5),
                    ],
                    'Profile' => [
                        'LoginID' => $this->cred('login_id'),
                        'LicenceKey' => $this->cred('licence_key'),
                        'Api_type' => 'S',
                    ],
                ]);
            return $this->handle($res, 'pickup');
        } catch (\Throwable $e) {
            return $this->error('pickup', $e);
        }
    }

    public function generateLabel(Shipment $shipment): array
    {
        return ['message' => 'Blue Dart labels are returned inline as base64 PDF in waybill.create response.'];
    }

    public function generateInvoice(Shipment $shipment): array
    {
        return ['message' => 'Blue Dart does not expose invoice API.'];
    }

    public function track(Shipment $shipment): array
    {
        try {
            $res = Http::withHeaders($this->headers())->timeout(15)
                ->post($this->baseUrl() . '/in/transportation/tracking/v1/Tracking', [
                    'Request' => [
                        'AWBNo' => [$shipment->awb_code],
                    ],
                    'Profile' => [
                        'LoginID' => $this->cred('login_id'),
                        'LicenceKey' => $this->cred('licence_key'),
                    ],
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
                ->post($this->baseUrl() . '/in/transportation/waybill/v1/CancelWaybill', [
                    'Request' => [
                        'AWBNo' => $shipment->awb_code,
                        'Reason' => $reason ?? 'Admin cancellation',
                    ],
                    'Profile' => [
                        'LoginID' => $this->cred('login_id'),
                        'LicenceKey' => $this->cred('licence_key'),
                    ],
                ]);
            return $this->handle($res, 'cancel');
        } catch (\Throwable $e) {
            return $this->error('cancel', $e);
        }
    }

    private function handle(\Illuminate\Http\Client\Response $res, string $ctx): array
    {
        if (! $res->successful()) {
            Log::warning('BlueDart non-2xx', ['ctx' => $ctx, 'status' => $res->status(), 'body' => $res->body()]);
            return ['error' => true, 'status' => $res->status(), 'message' => $res->body()];
        }
        return $res->json() ?? [];
    }

    private function error(string $ctx, \Throwable $e): array
    {
        Log::error("BlueDart {$ctx} exception: " . $e->getMessage());
        return ['error' => true, 'message' => $e->getMessage()];
    }
}
