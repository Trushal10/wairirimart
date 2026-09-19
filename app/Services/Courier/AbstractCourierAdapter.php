<?php

namespace App\Services\Courier;

use App\Contracts\CourierAdapterContract;
use App\Models\DeliveryPartner;
use App\Models\Shipment;

abstract class AbstractCourierAdapter implements CourierAdapterContract
{
    public function __construct(protected DeliveryPartner $model)
    {
    }

    protected function cred(string $key, $default = null)
    {
        $creds = $this->model->credentials ?? [];
        return $creds[$key] ?? $default;
    }

    protected function config(string $key, $default = null)
    {
        $cfg = $this->model->config ?? [];
        return $cfg[$key] ?? $default;
    }

    public function isTestMode(): bool
    {
        return $this->model->mode !== 'live';
    }

    /**
     * Default status normalization — subclasses can override for provider-specific strings.
     */
    public function normalizeStatus(?string $providerStatus): ?string
    {
        if (empty($providerStatus)) return null;
        $s = strtolower(trim($providerStatus));
        return match (true) {
            str_contains($s, 'delivered') => Shipment::STATUS_DELIVERED,
            str_contains($s, 'rto') => Shipment::STATUS_RTO,
            str_contains($s, 'cancel') => Shipment::STATUS_CANCELLED,
            str_contains($s, 'out for delivery') || $s === 'ofd' => Shipment::STATUS_OUT_FOR_DELIVERY,
            str_contains($s, 'in transit') || str_contains($s, 'shipped') || str_contains($s, 'dispatched') => Shipment::STATUS_IN_TRANSIT,
            str_contains($s, 'picked up') || str_contains($s, 'pickup done') || str_contains($s, 'pickup complete') => Shipment::STATUS_PICKED_UP,
            str_contains($s, 'pickup scheduled') || str_contains($s, 'pickup awaited') => Shipment::STATUS_PICKUP_SCHEDULED,
            str_contains($s, 'awb') && str_contains($s, 'assigned') => Shipment::STATUS_AWB_ASSIGNED,
            str_contains($s, 'awb generated') => Shipment::STATUS_AWB_ASSIGNED,
            default => null,
        };
    }
}
