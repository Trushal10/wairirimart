<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    // High-level statuses we normalize to, independent of provider text.
    public const STATUS_PENDING = 'pending';
    public const STATUS_ORDER_CREATED = 'order_created';
    public const STATUS_AWB_ASSIGNED = 'awb_assigned';
    public const STATUS_PICKUP_SCHEDULED = 'pickup_scheduled';
    public const STATUS_PICKED_UP = 'picked_up';
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_OUT_FOR_DELIVERY = 'out_for_delivery';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_RTO = 'rto';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_FAILED = 'failed';

    public const TERMINAL_STATUSES = [
        self::STATUS_DELIVERED,
        self::STATUS_RTO,
        self::STATUS_CANCELLED,
        self::STATUS_FAILED,
    ];

    protected $fillable = [
        'order_id',
        'delivery_partner_id',
        'provider',
        'provider_order_id',
        'provider_shipment_id',
        'awb_code',
        'courier_id',
        'courier_name',
        'tracking_url',
        'label_url',
        'manifest_url',
        'invoice_url',
        'status',
        'remark',
        'weight',
        'length',
        'breadth',
        'height',
        'pickup_scheduled_date',
        'shipped_at',
        'delivered_at',
        'meta',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
        'length' => 'decimal:2',
        'breadth' => 'decimal:2',
        'height' => 'decimal:2',
        'pickup_scheduled_date' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'meta' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function deliveryPartner(): BelongsTo
    {
        return $this->belongsTo(DeliveryPartner::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    /**
     * Fully-qualified columns useful for showing shipment status in listings.
     *
     * MUST be used whenever eager-loading via `latestOfMany` relations
     * (e.g. Order::latestShipment) with a constrained select — otherwise
     * the `order_id` reference is ambiguous between the base table and
     * the subquery join Laravel emits.
     *
     * @return array<int, string>
     */
    public static function summaryColumns(): array
    {
        return [
            'shipments.id',
            'shipments.order_id',
            'shipments.provider',
            'shipments.status',
            'shipments.awb_code',
            'shipments.courier_name',
            'shipments.tracking_url',
            'shipments.updated_at',
        ];
    }
}
