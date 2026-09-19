<?php

namespace App\Services\Courier;

use App\Contracts\CourierAdapterContract;
use App\Models\DeliveryPartner;

class CourierManager
{
    public function forCode(string $code): CourierAdapterContract
    {
        $model = DeliveryPartner::query()
            ->where('code', $code)
            ->where('is_active', true)
            ->firstOrFail();
        return $this->make($model);
    }

    public function forModel(DeliveryPartner $model): CourierAdapterContract
    {
        return $this->make($model);
    }

    public function default(): ?CourierAdapterContract
    {
        $model = DeliveryPartner::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderByDesc('priority')
            ->first();
        return $model ? $this->make($model) : null;
    }

    /** @return array<int, CourierAdapterContract> */
    public function activePartners(): array
    {
        return DeliveryPartner::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderByDesc('priority')
            ->get()
            ->map(fn ($m) => $this->make($m))
            ->all();
    }

    protected function make(DeliveryPartner $model): CourierAdapterContract
    {
        $class = match ($model->code) {
            DeliveryPartner::CODE_SHIPROCKET => ShiprocketAdapter::class,
            DeliveryPartner::CODE_DELHIVERY => DelhiveryAdapter::class,
            DeliveryPartner::CODE_BLUEDART => BlueDartAdapter::class,
            DeliveryPartner::CODE_DTDC => DtdcAdapter::class,
            DeliveryPartner::CODE_XPRESSBEES => XpressbeesAdapter::class,
            DeliveryPartner::CODE_SHADOWFAX => ShadowfaxAdapter::class,
            default => throw new \RuntimeException("Unsupported courier: {$model->code}"),
        };
        return new $class($model);
    }
}
