<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGatewayContract;
use App\Models\PaymentGateway;

abstract class AbstractPaymentGateway implements PaymentGatewayContract
{
    public function __construct(protected PaymentGateway $model)
    {
    }

    /** Underlying DB row — needed by callers that must access credentials directly (e.g. raw SDK use). */
    public function getModel(): PaymentGateway
    {
        return $this->model;
    }

    protected function cred(string $key, $default = null)
    {
        $creds = $this->model->credentials ?? [];
        return $creds[$key] ?? $default;
    }

    protected function config(string $key, $default = null)
    {
        $config = $this->model->config ?? [];
        return $config[$key] ?? $default;
    }

    public function isTestMode(): bool
    {
        return $this->model->mode !== 'live';
    }
}
