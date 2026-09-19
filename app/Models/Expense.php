<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Period-based operating expense (rent, salaries, marketing, etc.).
 * Consumed by the P&L report to compute Net Profit.
 */
class Expense extends Model
{
    public const CATEGORIES = [
        'rent' => 'Rent',
        'salary' => 'Salary',
        'marketing' => 'Marketing',
        'utility' => 'Utility',
        'logistics' => 'Logistics',
        'software' => 'Software',
        'inventory' => 'Inventory',
        'tax' => 'Tax',
        'other' => 'Other',
    ];

    protected $fillable = [
        'date',
        'category',
        'title',
        'note',
        'amount',
        'user_id',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeInRange($query, $from, $to)
    {
        return $query
            ->when($from, fn ($q, $d) => $q->whereDate('date', '>=', $d))
            ->when($to, fn ($q, $d) => $q->whereDate('date', '<=', $d));
    }
}
