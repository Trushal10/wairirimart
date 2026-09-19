<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_PARTIALLY_REFUNDED = 'partially_refunded';

    protected $fillable = [
        'order_id',
        'type',
        'payment_id',
        'refund_id',
        'status',
        'amount',
        'refunded_amount',
        'gateway_fee',
        'refunded_at',
        'failure_reason',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'gateway_fee' => 'decimal:2',
        'refunded_at' => 'datetime',
        'meta' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isRefundable(): bool
    {
        return in_array($this->status, [self::STATUS_PAID, self::STATUS_PARTIALLY_REFUNDED], true)
            && $this->type === 'razorpay'
            && (float) $this->refunded_amount < (float) $this->amount;
    }
}
