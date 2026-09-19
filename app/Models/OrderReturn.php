<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/*
 * Represents a customer's return request against an order.
 *
 * Named OrderReturn (not Return) because `return` is a PHP reserved word — the
 * table itself is still `returns` for readability, mapped via `$table`.
 */
class OrderReturn extends Model
{
    use SoftDeletes;

    protected $table = 'returns';

    public const STATUS_REQUESTED = 'requested';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_REQUESTED,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_RECEIVED,
        self::STATUS_REFUNDED,
        self::STATUS_CANCELLED,
    ];

    public const REASON_DAMAGED = 'damaged';
    public const REASON_WRONG_ITEM = 'wrong_item';
    public const REASON_NOT_AS_DESCRIBED = 'not_as_described';
    public const REASON_SIZE_ISSUE = 'size_issue';
    public const REASON_OTHER = 'other';

    public const REASONS = [
        self::REASON_DAMAGED => 'Arrived damaged / broken',
        self::REASON_WRONG_ITEM => 'Wrong item was sent',
        self::REASON_NOT_AS_DESCRIBED => 'Item is not as described',
        self::REASON_SIZE_ISSUE => 'Size / age suitability issue',
        self::REASON_OTHER => 'Other',
    ];

    protected $fillable = [
        'return_no',
        'order_id',
        'customer_id',
        'status',
        'reason',
        'comment',
        'photo',
        'rejection_reason',
        'refund_amount',
        'restock_on_receipt',
        'requested_at',
        'approved_at',
        'rejected_at',
        'received_at',
        'refunded_at',
    ];

    protected $casts = [
        'refund_amount' => 'decimal:2',
        'restock_on_receipt' => 'boolean',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'received_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnItem::class, 'return_id');
    }

    /* -------------------- state helpers -------------------- */

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_REJECTED,
            self::STATUS_REFUNDED,
            self::STATUS_CANCELLED,
        ], true);
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? ucfirst(str_replace('_', ' ', (string) $this->reason));
    }

    /* -------------------- return-number generator -------------------- */

    /**
     * Human-friendly, sortable, easy to read on the phone.
     * Format: RET-YYYYMMDD-XXXX (XXXX = zero-padded id).
     */
    public static function generateReturnNo(int $sequence): string
    {
        return sprintf('RET-%s-%04d', now()->format('Ymd'), $sequence);
    }
}
