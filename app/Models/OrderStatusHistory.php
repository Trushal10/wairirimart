<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusHistory extends Model
{
    public const SOURCE_SYSTEM = 'system';
    public const SOURCE_ADMIN = 'admin';
    public const SOURCE_WEBHOOK = 'webhook';
    public const SOURCE_CUSTOMER = 'customer';

    protected $fillable = [
        'order_id',
        'shipment_id',
        'status',
        'source',
        'comment',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
