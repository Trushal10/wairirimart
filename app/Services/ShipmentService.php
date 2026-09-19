<?php

namespace App\Services;

use App\Contracts\CourierAdapterContract;
use App\Models\DeliveryPartner;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Shipment;
use App\Notifications\OrderShipped;
use App\Services\Courier\CourierManager;
use App\Services\SmsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShipmentService
{
    public function __construct(protected CourierManager $couriers)
    {
    }

    public function createForOrder(Order $order, DeliveryPartner $partner): Shipment
    {
        return DB::transaction(function () use ($order, $partner) {
            $order->loadMissing('orderItems.product', 'payment');

            $shipment = Shipment::firstOrNew(
                ['order_id' => $order->id],
                ['delivery_partner_id' => $partner->id, 'provider' => $partner->code, 'status' => Shipment::STATUS_PENDING]
            );
            $shipment->delivery_partner_id = $partner->id;
            $shipment->provider = $partner->code;
            $shipment->save();

            if (! $partner->is_active) {
                $shipment->status = Shipment::STATUS_FAILED;
                $shipment->remark = 'Partner is disabled.';
                $shipment->save();
                $this->logHistory($order, $shipment, Shipment::STATUS_FAILED, 'Partner disabled.', OrderStatusHistory::SOURCE_SYSTEM);
                return $shipment;
            }

            try {
                $adapter = $this->couriers->forModel($partner);
                $result = $adapter->createShipment($order);
            } catch (\Throwable $e) {
                Log::error('createShipment adapter error: ' . $e->getMessage());
                $shipment->status = Shipment::STATUS_FAILED;
                $shipment->remark = $e->getMessage();
                $shipment->save();
                return $shipment;
            }

            if (! empty($result['error'])) {
                $shipment->status = Shipment::STATUS_FAILED;
                $shipment->remark = $result['message'] ?? 'Shipment creation failed.';
                $shipment->meta = array_merge((array) $shipment->meta, ['create' => $result]);
                $shipment->save();
                $this->logHistory($order, $shipment, Shipment::STATUS_FAILED, 'Create failed: ' . ($result['message'] ?? 'unknown'), OrderStatusHistory::SOURCE_SYSTEM, $result);
                return $shipment;
            }

            $providerOrderId    = data_get($result, 'order_id')            ?: data_get($result, 'data.order_id');
            $providerShipmentId = data_get($result, 'shipment_id')         ?: data_get($result, 'data.shipment_id');
            $awbCode            = data_get($result, 'awb_code')            ?: data_get($result, 'data.awb_code');
            $courierId          = data_get($result, 'courier_company_id')  ?: data_get($result, 'data.courier_company_id');
            $courierName        = data_get($result, 'courier_name')        ?: data_get($result, 'data.courier_name');

            $shipment->provider_order_id    = $providerOrderId ?: $shipment->provider_order_id;
            $shipment->provider_shipment_id = $providerShipmentId ?: $shipment->provider_shipment_id;
            $shipment->awb_code             = $awbCode           ?: $shipment->awb_code;
            $shipment->courier_id           = $courierId         ?: $shipment->courier_id;
            $shipment->courier_name         = $courierName       ?: $shipment->courier_name;
            $shipment->meta = array_merge((array) $shipment->meta, ['create' => $result]);

            // Guard: Shiprocket sometimes returns a truthy response but a 0/null
            // shipment_id when the account's pickup_location or channel_id isn't
            // configured. Persisting a null shipment_id is what caused every
            // subsequent Assign-AWB call to fail with "Required field missing".
            // Surface it as a FAILED shipment so the admin gets an actionable error.
            if ((int) $shipment->provider_shipment_id <= 0) {
                $shipment->status = Shipment::STATUS_FAILED;
                $shipment->remark = 'Shipment created on ' . $partner->name
                    . ' but no shipment_id was returned. Check pickup_location / channel_id.';
                $shipment->save();
                $this->logHistory($order, $shipment, Shipment::STATUS_FAILED,
                    'Create returned no shipment_id — verify Shiprocket pickup_location.',
                    OrderStatusHistory::SOURCE_SYSTEM, $result);
                return $shipment;
            }

            $shipment->status = Shipment::STATUS_ORDER_CREATED;
            $shipment->remark = 'Shipment created on ' . $partner->name . '.';
            $shipment->save();

            $this->logHistory($order, $shipment, Shipment::STATUS_ORDER_CREATED,
                'Shipment created on ' . $partner->name, OrderStatusHistory::SOURCE_SYSTEM);

            $order->delivery_partner_id = $partner->id;
            $order->shipping_provider = $partner->code;
            $order->shipping_tracking_number = $shipment->awb_code;
            if ($order->status === Order::PENDING) {
                $order->status = Order::CONFIRMED;
                $this->logHistory($order, $shipment, Order::CONFIRMED, 'Order confirmed after shipment.', OrderStatusHistory::SOURCE_SYSTEM);
            }
            $order->save();

            return $shipment;
        });
    }

    public function assignAwb(Shipment $shipment, ?string $courierId = null): Shipment
    {
        // Self-heal: if the shipment has no provider_shipment_id yet (previous
        // createShipment returned OK but Shiprocket didn't attach it — usually a
        // pickup_location mismatch), re-run createForOrder before hitting AWB.
        // That way the operator can fix the Shiprocket config and just click
        // "Assign AWB" — the create step gets retried transparently.
        if ((int) $shipment->provider_shipment_id <= 0 && $shipment->deliveryPartner) {
            $shipment->load('order.orderItems.product', 'order.payment', 'deliveryPartner');
            if ($shipment->order && $shipment->deliveryPartner) {
                $this->createForOrder($shipment->order, $shipment->deliveryPartner);
                $shipment->refresh();
            }
        }

        // Still missing? Surface Shiprocket's actual create-time error message
        // (from meta.create) so the admin sees "Wrong pickup location" instead
        // of a generic guard message.
        if ((int) $shipment->provider_shipment_id <= 0) {
            $rawError = data_get((array) $shipment->meta, 'create.message')
                ?? data_get((array) $shipment->meta, 'create.error')
                ?? 'Shiprocket did not return a shipment_id. Fix pickup_location / channel_id in the delivery partner settings, then try again.';
            $shipment->remark = 'AWB not assigned: ' . $rawError;
            $shipment->save();
            $this->logHistory($shipment->order, $shipment, $shipment->status,
                'assign_awb blocked: ' . $rawError, OrderStatusHistory::SOURCE_SYSTEM,
                ['create_response' => data_get((array) $shipment->meta, 'create')]);
            return $shipment;
        }

        $shipment = $this->runAdapter($shipment, 'assign_awb', function (CourierAdapterContract $a) use ($shipment, $courierId) {
            return $a->assignAwb($shipment, $courierId);
        }, function (Shipment $s, array $result) {
            $data = $result['response']['data'] ?? $result['data'] ?? $result;
            $s->awb_code = data_get($data, 'awb_code', $s->awb_code);
            $s->courier_id = data_get($data, 'courier_company_id', $s->courier_id);
            $s->courier_name = data_get($data, 'courier_name', $s->courier_name);
            $s->status = Shipment::STATUS_AWB_ASSIGNED;
            $s->remark = 'AWB assigned.';
            return 'AWB assigned: ' . ($s->awb_code ?? '-');
        });

        // Notify customer once AWB is on the shipment (first time only — status change guards it)
        $this->notifyCustomerShipped($shipment);

        return $shipment;
    }

    /**
     * Send the OrderShipped email if the shipment has an AWB and hasn't already
     * been announced. Guarded by a meta flag so status re-syncs don't re-send.
     */
    protected function notifyCustomerShipped(Shipment $shipment): void
    {
        if (! $shipment->awb_code) return;

        $meta = (array) $shipment->meta;
        if (! empty($meta['ship_notified_at'])) return;

        try {
            $customer = $shipment->order?->customer;
            if ($customer) {
                $customer->notify(new OrderShipped($shipment->order, $shipment));
                if ($shipment->order->shipping_phone) {
                    $trackBits = $shipment->awb_code ? " AWB: {$shipment->awb_code}." : '';
                    app(SmsService::class)->send(
                        $shipment->order->shipping_phone,
                        "Hi {$customer->name}, your order #{$shipment->order->order_no} has been shipped.{$trackBits} - " . config('app.name')
                    );
                }
                $shipment->meta = array_merge($meta, ['ship_notified_at' => now()->toIso8601String()]);
                $shipment->save();
            }
        } catch (\Throwable $e) {
            Log::warning('OrderShipped notification failed: ' . $e->getMessage());
        }
    }

    public function requestPickup(Shipment $shipment): Shipment
    {
        return $this->runAdapter($shipment, 'pickup', function (CourierAdapterContract $a) use ($shipment) {
            return $a->requestPickup($shipment);
        }, function (Shipment $s, array $result) {
            $pickupDate = data_get($result, 'response.pickup_scheduled_date')
                ?? data_get($result, 'pickup_scheduled_date');
            if ($pickupDate) $s->pickup_scheduled_date = $pickupDate;
            $s->status = Shipment::STATUS_PICKUP_SCHEDULED;
            $s->remark = 'Pickup scheduled.';
            return 'Pickup scheduled' . ($pickupDate ? " for {$pickupDate}" : '.');
        });
    }

    public function generateLabel(Shipment $shipment): Shipment
    {
        return $this->runAdapter($shipment, 'label', function (CourierAdapterContract $a) use ($shipment) {
            return $a->generateLabel($shipment);
        }, function (Shipment $s, array $result) {
            // Different couriers return the label URL under different keys — try every
            // reasonable shape before giving up. This covers:
            //   Shiprocket:  { "label_url": "https://..." }
            //   Shadowfax:   { "label_url": "https://..." } (normalised in adapter)
            //   Xpressbees:  { "data": { "label_url": "..." } } or { "data": [{ "label_url": "..." }] }
            //   DTDC:        { "response": { "label_url": "..." } }
            //   Generic:     { "labels": [{ "url": "..." }] }
            $candidates = [
                'label_url',
                'pdf_link',
                'url',
                'response.label_url',
                'response.data.label_url',
                'data.label_url',
                'data.0.label_url',
                'data.pdf_url',
                'labels.0.url',
                'labels.0.label_url',
                'shipment_label',
            ];
            $url = null;
            foreach ($candidates as $key) {
                $val = data_get($result, $key);
                if (is_string($val) && filter_var($val, FILTER_VALIDATE_URL)) {
                    $url = $val;
                    break;
                }
            }
            if ($url) {
                $s->label_url = $url;
                $s->order?->update(['shipping_label_url' => $url]);
                return 'Label generated.';
            }
            return 'Label request completed but no URL returned.';
        });
    }

    public function syncTracking(Shipment $shipment): Shipment
    {
        return $this->runAdapter($shipment, 'track', function (CourierAdapterContract $a) use ($shipment) {
            return $a->track($shipment);
        }, function (Shipment $s, array $result) {
            $adapter = $this->couriers->forModel($s->deliveryPartner);
            $providerStatus = data_get($result, 'tracking_data.shipment_status')
                ?? data_get($result, 'tracking_data.shipment_track.current_status')
                ?? data_get($result, 'current_status')
                ?? data_get($result, 'shipment_status');
            $normalized = $adapter->normalizeStatus((string) $providerStatus);
            $comment = 'Tracking sync: ' . ($providerStatus ?? 'no change');
            if ($normalized && $normalized !== $s->status) {
                $s->status = $normalized;
                if ($normalized === Shipment::STATUS_DELIVERED) {
                    $s->delivered_at = now();
                    $this->syncOrderStatus($s->order, Order::COMPLETED, 'Delivered per courier update.');
                } elseif ($normalized === Shipment::STATUS_PICKED_UP && ! $s->shipped_at) {
                    $s->shipped_at = now();
                } elseif ($normalized === Shipment::STATUS_CANCELLED) {
                    $this->syncOrderStatus($s->order, Order::CANCELLED, 'Cancelled per courier update.');
                }
                $s->remark = $comment;
            }
            $s->meta = array_merge((array) $s->meta, ['last_tracking' => $result]);
            return $comment;
        });
    }

    public function cancel(Shipment $shipment, string $reason = 'Cancelled from admin.'): Shipment
    {
        return $this->runAdapter($shipment, 'cancel', function (CourierAdapterContract $a) use ($shipment, $reason) {
            return $a->cancel($shipment, $reason);
        }, function (Shipment $s, array $result) use ($reason) {
            $s->status = Shipment::STATUS_CANCELLED;
            $s->remark = $reason;
            $this->syncOrderStatus($s->order, Order::CANCELLED, $reason);
            return $reason;
        }, OrderStatusHistory::SOURCE_ADMIN);
    }

    public function handleTrackingWebhook(array $payload): void
    {
        $awb = $payload['awb'] ?? $payload['awb_code'] ?? null;
        $orderNo = $payload['order_id'] ?? null;
        $status = $payload['current_status'] ?? $payload['shipment_status'] ?? null;

        $shipment = null;
        if ($awb) $shipment = Shipment::where('awb_code', $awb)->latest('id')->first();
        if (! $shipment && $orderNo) {
            $order = Order::where('order_no', $orderNo)->first();
            if ($order) $shipment = $order->latestShipment;
        }
        if (! $shipment) {
            Log::info('Tracking webhook: no matching shipment', ['payload' => $payload]);
            return;
        }

        $adapter = $shipment->deliveryPartner ? $this->couriers->forModel($shipment->deliveryPartner) : null;
        $normalized = $adapter ? $adapter->normalizeStatus((string) $status) : null;

        if ($normalized && $normalized !== $shipment->status) {
            $shipment->status = $normalized;
            if ($normalized === Shipment::STATUS_DELIVERED) {
                $shipment->delivered_at = now();
                $this->syncOrderStatus($shipment->order, Order::COMPLETED, 'Delivered (webhook).');
            }
            if ($normalized === Shipment::STATUS_PICKED_UP && ! $shipment->shipped_at) {
                $shipment->shipped_at = now();
            }
            if ($normalized === Shipment::STATUS_CANCELLED) {
                $this->syncOrderStatus($shipment->order, Order::CANCELLED, 'Cancelled (webhook).');
            }
            $this->logHistory($shipment->order, $shipment, $normalized,
                'Webhook status: ' . $status, OrderStatusHistory::SOURCE_WEBHOOK, $payload);
        }
        $shipment->meta = array_merge((array) $shipment->meta, ['last_webhook' => $payload]);
        $shipment->save();
    }

    /* -------------------- helpers -------------------- */

    protected function runAdapter(Shipment $shipment, string $action, \Closure $call, \Closure $onSuccess, string $source = OrderStatusHistory::SOURCE_SYSTEM): Shipment
    {
        try {
            $adapter = $this->couriers->forModel($shipment->deliveryPartner);
            $result = $call($adapter);
        } catch (\Throwable $e) {
            Log::error("Shipment {$action} exception", ['message' => $e->getMessage(), 'id' => $shipment->id]);
            $this->logHistory($shipment->order, $shipment, $shipment->status, "{$action} failed: " . $e->getMessage(), $source);
            return $shipment;
        }
        if (! empty($result['error'])) {
            $this->logHistory($shipment->order, $shipment, $shipment->status,
                "{$action} failed: " . ($result['message'] ?? 'unknown'), $source, $result);
            $shipment->remark = $result['message'] ?? $shipment->remark;
            $shipment->save();
            return $shipment;
        }
        $comment = $onSuccess($shipment, $result) ?: "{$action} completed";
        $shipment->save();
        $this->logHistory($shipment->order, $shipment, $shipment->status, $comment, $source, is_array($result) ? $result : null);
        return $shipment;
    }

    protected function syncOrderStatus(?Order $order, string $status, string $comment): void
    {
        if (! $order || $order->status === $status) return;
        $order->status = $status;
        $order->save();
        $this->logHistory($order, $order->latestShipment, $status, $comment, OrderStatusHistory::SOURCE_SYSTEM);
    }

    protected function logHistory(?Order $order, ?Shipment $shipment, string $status, string $comment, string $source, ?array $meta = null): void
    {
        if (! $order) return;
        try {
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'shipment_id' => $shipment?->id,
                'status' => $status,
                'source' => $source,
                'comment' => mb_substr($comment, 0, 500),
                'meta' => $meta,
            ]);
        } catch (\Throwable $e) {
            Log::warning('logHistory failed: ' . $e->getMessage());
        }
    }
}
