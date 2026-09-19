<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAddress extends Model
{
    const HOME = 'Home';
    const WORK = 'Work';

    const TYPES = [
        self::HOME => 'home',
        self::WORK => 'work',
    ];

    protected $fillable = [
        'customer_id',
        'name',
        'email',
        'phone',
        'city',
        'pincode',
        'state',
        'address',
        'type',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
