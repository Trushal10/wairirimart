<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGatewayContract;
use App\Models\PaymentGateway;

class PaymentGatewayManager
{
    /**
     * Instantiate a gateway adapter for a given code.
     */
    public function forCode(string $code): PaymentGatewayContract
    {
        $model = PaymentGateway::query()
            ->where('code', $code)
            ->where('is_active', true)
            ->firstOrFail();
        return $this->make($model);
    }

    public function forModel(PaymentGateway $model): PaymentGatewayContract
    {
        return $this->make($model);
    }

    public function default(): ?PaymentGatewayContract
    {
        $model = PaymentGateway::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderByDesc('priority')
            ->first();
        return $model ? $this->make($model) : null;
    }

    /** @return array<int, PaymentGatewayContract> */
    public function activeGateways(): array
    {
        return PaymentGateway::where('is_active', true)
            ->orderByDesc('is_default')
            ->orderByDesc('priority')
            ->get()
            ->map(fn ($m) => $this->make($m))
            ->all();
    }

    protected function make(PaymentGateway $model): PaymentGatewayContract
    {
        $class = match ($model->code) {
            PaymentGateway::CODE_RAZORPAY => RazorpayGateway::class,
            PaymentGateway::CODE_STRIPE => StripeGateway::class,
            PaymentGateway::CODE_PAYPAL => PayPalGateway::class,
            PaymentGateway::CODE_COD => CodGateway::class,
            default => throw new \RuntimeException("Unsupported payment gateway: {$model->code}"),
        };
        return new $class($model);
    }
}
