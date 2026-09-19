<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryPartner extends Model
{
    public const CODE_SHIPROCKET = 'shiprocket';
    public const CODE_DELHIVERY = 'delhivery';
    public const CODE_BLUEDART = 'bluedart';
    public const CODE_DTDC = 'dtdc';
    public const CODE_XPRESSBEES = 'xpressbees';
    public const CODE_SHADOWFAX = 'shadowfax';
    public const CODE_LOCAL = 'local_delivery';

    protected $fillable = [
        'name',
        'code',
        'description',
        'logo',
        'is_third_party',
        'is_active',
        'is_default',
        'mode',
        'api_base_url',
        'api_key',
        'credentials',
        'config',
        'supports',
        'priority',
    ];

    protected $casts = [
        'is_third_party' => 'boolean',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'credentials' => 'encrypted:array',
        'config' => 'array',
        'supports' => 'array',
        'priority' => 'integer',
    ];

    protected $hidden = ['credentials', 'api_key'];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    /**
     * Public-safe snapshot with credential presence flags but no secret values.
     *
     * Pass the adapter's credential schema to also get back the values that
     * are not secrets — an account slug or public client ID, say — so the
     * admin can see what is configured. Without a schema every field is
     * treated as a secret.
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
