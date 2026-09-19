<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    const PENDING = 'pending';
    const CONFIRMED = 'confirmed';
    const COMPLETED = 'delivered';
    const CANCELLED = 'canceled';

    const STATUS = [
        self::PENDING => 'pending',
        self::CONFIRMED => 'confirmed',
        self::COMPLETED => 'delivered',
        self::CANCELLED => 'canceled',
    ];

    protected $fillable = [
        'order_no',
        'customer_id',
        'sub_total',
        'shipping',
        'discount',
        'tax_amount',
        'other_expense',
        'coupan_code',
        'total',
        'status',
        'stock_committed_at',
        'shipping_name',
        'shipping_email',
        'shipping_phone',
        'shipping_city',
        'shipping_pincode',
        'shipping_state',
        'shipping_address',
        'delivery_partner_id',
        'shipping_provider',
        'shipping_tracking_number',
        'shipping_label_url',
    ];

    protected $casts = [
        'stock_committed_at' => 'datetime',
        'sub_total' => 'decimal:2',
        'shipping' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'other_expense' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class, 'order_id', 'id');
    }

    public function deliveryPartner(): BelongsTo
    {
        return $this->belongsTo(DeliveryPartner::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function latestShipment(): HasOne
    {
        return $this->hasOne(Shipment::class)->latestOfMany();
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(OrderReturn::class);
    }

    /* -------------------- cancellation eligibility -------------------- */

    /**
     * A customer may cancel their own order right up until it ships.
     *
     * Pending orders always qualify. A confirmed order qualifies while no
     * shipment is live for it: once one is booked with the courier (even
     * before an AWB), cancelling would leave a parcel in the courier's
     * system, so from then on it is a support conversation — or a return,
     * once delivered. A shipment that was itself cancelled or failed does not
     * count.
     *
     * A paid online order cancelled this way still needs its refund issued
     * from the admin order page; see isRefundDueOnCancel().
     *
     * Deliberately excludes orders already cancelled, so a double submit or a
     * stale page cannot run the stock restore twice.
     */
    public function isCancellableByCustomer(): bool
    {
        if ($this->status === self::PENDING) {
            return true;
        }
        if ($this->status !== self::CONFIRMED) {
            return false;
        }

        $shipment = $this->latestShipment;

        return ! $shipment
            || in_array($shipment->status, [Shipment::STATUS_CANCELLED, Shipment::STATUS_FAILED], true);
    }

    /** Money was captured online, so cancelling means a refund is owed. */
    public function isRefundDueOnCancel(): bool
    {
        return (bool) $this->payment?->isRefundable();
    }

    /* -------------------- return eligibility -------------------- */

    /**
     * The customer can request a return only when the order is delivered AND
     * we're inside the return window. Window is configurable via
     * `services.returns.window_days` — defaults to 14 days from delivered_at
     * (or `updated_at` when we haven't captured a discrete delivered timestamp).
     */
    public function isReturnable(): bool
    {
        if ($this->status !== self::COMPLETED) return false;

        $shipment = $this->latestShipment;
        $deliveredAt = $shipment?->delivered_at ?? $this->updated_at;
        if (! $deliveredAt) return false;

        $windowDays = (int) config('services.returns.window_days', 14);
        return $deliveredAt->copy()->addDays($windowDays)->isFuture();
    }
}
