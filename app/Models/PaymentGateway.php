<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentGateway extends Model
{
    public const CODE_RAZORPAY = 'razorpay';
    public const CODE_STRIPE = 'stripe';
    public const CODE_PAYPAL = 'paypal';
    public const CODE_COD = 'cod';

    protected $fillable = [
        'code',
        'name',
        'description',
        'logo',
        'mode',
        'is_active',
        'is_default',
        'credentials',
        'config',
        'supports',
        'priority',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'credentials' => 'encrypted:array',
        'config' => 'array',
        'supports' => 'array',
        'priority' => 'integer',
    ];

    protected $hidden = ['credentials'];

    /**
     * Public-safe snapshot with credential presence flags but no secret values.
     *
     * Pass the adapter's credential schema to also get back the values that
     * are not secrets — a Razorpay key_id, for example, is published to every
     * checkout page anyway, so masking it in the admin hides nothing from an
     * attacker while stopping the admin from seeing which account is wired up.
     * Without a schema every field is treated as a secret.
     */
    public function toSafeArray(array $schema = []): array
    {
        $creds = $this->credentials ?? [];
        $masked = [];
        $public = [];
        foreach ($creds as $k => $v) {
            $masked[$k] = $this->maskValue((string) $v);
            if (($schema[$k]['type'] ?? 'password') !== 'password') {
                $public[$k] = (string) $v;
            }
        }
        return array_merge($this->toArray(), [
            'credentials_present' => ! empty($creds),
            'credentials_masked' => $masked,
            'credentials_public' => $public,
        ]);
    }

    private function maskValue(string $v): string
    {
        $len = strlen($v);
        if ($len <= 4) {
            return str_repeat('•', max($len, 4));
        }
        return str_repeat('•', $len - 4) . substr($v, -4);
    }
}
