<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $fillable = [
        'product_id',
        'customer_id',
        'rate',
        'title',
        'review',
        'name',
        'email',
        'image',
        'is_approved',
        'is_spam',
    ];

    protected $casts = [
        'is_approved' => 'boolean',
        'is_spam' => 'boolean',
        'rate' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
