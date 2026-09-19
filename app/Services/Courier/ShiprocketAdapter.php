<?php

namespace App\Services\Courier;

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShiprocketAdapter extends AbstractCourierAdapter
{
    private const BASE = 'https://apiv2.shiprocket.in/v1/external';

    public function code(): string { return 'shiprocket'; }
    public function name(): string { return 'Shiprocket'; }
    public function supports(): array { return ['pickup', 'labels', 'tracking', 'manifest', 'invoice', 'cod', 'cancel', 'serviceability']; }

    public function credentialSchema(): array
    {
        return [
            'email' => ['label' => 'Account Email', 'type' => 'text', 'required' => true],
            'password' => ['label' => 'Password', 'type' => 'password', 'required' => true],
            'pickup_location' => ['label' => 'Pickup Location', 'type' => 'text', 'required' => true, 'help' => 'Name of a pickup location saved on Shiprocket.'],
            'pickup_pincode' => ['label' => 'Pickup Pincode', 'type' => 'text', 'required' => true],
            'channel_id' => ['label' => 'Channel ID', 'type' => 'text', 'required' => false],
            'webhook_token' => ['label' => 'Webhook Token', 'type' => 'password', 'required' => false, 'help' => 'Shared secret used in the Shiprocket webhook URL.'],
        ];
    }

    public function testConnection(): array
    {
        try {
            $token = $this->getToken(true);
            return $token
                ? ['ok' => true, 'message' => 'Authenticated with Shiprocket.']
                : ['ok' => false, 'message' => 'Shiprocket returned no token (check credentials).'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function checkServiceability(string $pickupPincode, string $deliveryPincode, float $weight = 0.5, int $codAmount = 0): array
    {
        try {
            $token = $this->getToken();
            if (! $token) return ['error' => true, 'message' => 'Auth failed.'];
            $res = Http::withToken($token)->asJson()->acceptJson()->timeout(15)
                ->get(self::BASE . '/courier/serviceability/', [
                    'pickup_postcode' => $pickupPincode,
                    'delivery_postcode' => $deliveryPincode,
                    'weight' => $weight,
                    'cod' => $codAmount > 0 ? 1 : 0,
                ]);
            return $this->handle($res, 'serviceability');
        } catch (\Throwable $e) {
            return $this->error('serviceability', $e);
        }
    }

    public function createShipment(Order $order): array
    {
        try {
            $token = $this->getToken();
            if (! $token) return ['error' => true, 'message' => 'Auth failed.'];
            $items = [];
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
            $payload = [
                'order_id' => $order->order_no,
                'order_date' => optional($order->created_at)->format('Y-m-d H:i') ?: now()->format('Y-m-d H:i'),
                'pickup_location' => $this->cred('pickup_location', 'Primary'),
                'channel_id' => $this->cred('channel_id') ?: null,
                'comment' => 'Created via API',
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
                'order_items' => $items,
                'payment_method' => optional($order->payment)->type === 'cod' ? 'COD' : 'Prepaid',
                'sub_total' => (float) $order->sub_total,
                'length' => (float) $this->config('default_length', 10),
                'breadth' => (float) $this->config('default_breadth', 10),
                'height' => (float) $this->config('default_height', 5),
                'weight' => (float) $this->config('default_weight', 0.5),
            ];
            $res = Http::withToken($token)->asJson()->acceptJson()->timeout(20)
                ->post(self::BASE . '/orders/create/adhoc', $payload);
            return $this->handle($res, 'orders/create/adhoc');
        } catch (\Throwable $e) {
            return $this->error('createShipment', $e);
        }
    }

    public function assignAwb(Shipment $shipment, ?string $courierId = null): array
    {
        // Shiprocket requires a POSITIVE integer shipment_id. array_filter would
        // silently drop null/0 and produce "Required field missing" from the API.
        $shipmentId = (int) ($shipment->provider_shipment_id ?? 0);
        if ($shipmentId <= 0) {
            return [
                'error' => true,
                'message' => 'assign_awb: provider_shipment_id is missing on this shipment. '
                    . 'Re-run createShipment and confirm Shiprocket returned a numeric shipment_id '
                    . '(check pickup_location / channel_id in the delivery partner settings).',
            ];
        }
        $body = ['shipment_id' => $shipmentId];
        if ($courierId !== null && $courierId !== '') {
            $body['courier_id'] = (int) $courierId;
        }
        return $this->post('courier/assign/awb', $body);
    }

    public function requestPickup(Shipment $shipment): array
    {
        return $this->post('courier/generate/pickup', ['shipment_id' => [$shipment->provider_shipment_id]]);
    }

    public function generateLabel(Shipment $shipment): array
    {
        return $this->post('courier/generate/label', ['shipment_id' => [$shipment->provider_shipment_id]]);
    }

    public function generateInvoice(Shipment $shipment): array
    {
        return $this->post('orders/print/invoice', ['ids' => [(int) $shipment->provider_order_id]]);
    }

    public function track(Shipment $shipment): array
    {
        try {
            $token = $this->getToken();
            if (! $token) return ['error' => true, 'message' => 'Auth failed.'];
            if (! empty($shipment->awb_code)) {
                $res = Http::withToken($token)->asJson()->acceptJson()->timeout(15)
                    ->get(self::BASE . '/courier/track/awb/' . $shipment->awb_code);
            } else {
                $res = Http::withToken($token)->asJson()->acceptJson()->timeout(15)
                    ->get(self::BASE . '/courier/track/shipment/' . $shipment->provider_shipment_id);
            }
            return $this->handle($res, 'track');
        } catch (\Throwable $e) {
            return $this->error('track', $e);
        }
    }

    public function cancel(Shipment $shipment, ?string $reason = null): array
    {
        if (! empty($shipment->awb_code)) {
            return $this->post('orders/cancel/shipment/awbs', ['awbs' => [$shipment->awb_code]]);
        }
        return $this->post('orders/cancel', ['ids' => [(int) $shipment->provider_order_id]]);
    }

    /* ---------------- helpers ---------------- */

    private function post(string $path, array $body): array
    {
        try {
            $token = $this->getToken();
            if (! $token) return ['error' => true, 'message' => 'Auth failed.'];
            // asJson() is essential — acceptJson() only sets the response header.
            // Without asJson(), the body goes as application/x-www-form-urlencoded
            // and Shiprocket's stricter endpoints return "Required field missing".
            $res = Http::withToken($token)->asJson()->acceptJson()->timeout(20)
                ->post(self::BASE . '/' . ltrim($path, '/'), $body);
            return $this->handle($res, $path);
        } catch (\Throwable $e) {
            return $this->error($path, $e);
        }
    }

    private function getToken(bool $forceRefresh = false): ?string
    {
        $key = 'shiprocket_token_' . $this->model->id;
        if ($forceRefresh) Cache::forget($key);
        return Cache::remember($key, 60 * 60 * 8, function () {
            $res = Http::asJson()->timeout(15)
                ->post(self::BASE . '/auth/login', [
                    'email' => $this->cred('email'),
                    'password' => $this->cred('password'),
                ]);
            if (! $res->successful()) {
                Log::error('Shiprocket auth failed', ['body' => $res->body()]);
                return null;
            }
            return $res->json('token');
        });
    }

    private function handle(\Illuminate\Http\Client\Response $res, string $ctx): array
    {
        if ($res->status() === 401) {
            Cache::forget('shiprocket_token_' . $this->model->id);
            return ['error' => true, 'message' => 'Shiprocket auth expired; retry.'];
        }
        if (! $res->successful()) {
            Log::warning('Shiprocket API non-2xx', ['ctx' => $ctx, 'status' => $res->status(), 'body' => $res->body()]);
            return ['error' => true, 'message' => $res->json('message') ?? 'Shiprocket API error.', 'status' => $res->status(), 'body' => $res->json()];
        }
        return $res->json() ?? [];
    }

    private function error(string $ctx, \Throwable $e): array
    {
        Log::error("Shiprocket {$ctx} exception: " . $e->getMessage());
        return ['error' => true, 'message' => $e->getMessage()];
    }

    public function normalizeStatus(?string $providerStatus): ?string
    {
        return parent::normalizeStatus($providerStatus);
    }
}
