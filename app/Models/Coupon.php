<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Coupon extends Model
{
    const TYPE_FIXED   = 'fixed';
    const TYPE_PERCENT = 'percent';

    protected $fillable = [
        'code',
        'type',
        'value',
        'min_order_amount',
        'max_discount_amount',
        'usage_limit',
        'used_count',
        'customer_id',
        'starts_at',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'value'              => 'decimal:2',
        'min_order_amount'   => 'decimal:2',
        'max_discount_amount'=> 'decimal:2',
        'is_active'          => 'boolean',
        'starts_at'          => 'datetime',
        'expires_at'         => 'datetime',
        'used_count'         => 'integer',
        'usage_limit'        => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function isValid(?int $customerId = null): bool
    {
        if (! $this->is_active) return false;
        if ($this->starts_at && $this->starts_at->isFuture()) return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) return false;
        if ($this->customer_id !== null && $this->customer_id !== $customerId) return false;
        return true;
    }

    /**
     * Compute the discount amount for the given subtotal.
     * Never exceeds the subtotal.
     */
    public function calculateDiscount(float $subtotal): float
    {
        if ($subtotal < (float) $this->min_order_amount) return 0.0;

        if ($this->type === self::TYPE_PERCENT) {
            $discount = $subtotal * ((float) $this->value / 100);
            if ($this->max_discount_amount !== null) {
                $discount = min($discount, (float) $this->max_discount_amount);
            }
        } else {
            $discount = (float) $this->value;
        }

        return min($discount, $subtotal);
    }

    public function incrementUsage(): void
    {
        $this->increment('used_count');
    }

    /**
     * Give a redemption back, for an order that was cancelled before it was
     * ever confirmed. Floored at zero so a double release can't drive the
     * counter negative and hand out more redemptions than the limit allows.
     */
    public function decrementUsage(): void
    {
        if ((int) $this->used_count <= 0) {
            return;
        }

        $this->decrement('used_count');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }
}
