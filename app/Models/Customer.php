<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Customer extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'address',
        'city',
        'image',
        'avatar',
        'provider',
        'google_id',
        'blocked_at',
        'block_reason',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'blocked_at'        => 'datetime',
        'password'          => 'hashed',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id', 'id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class, 'customer_id', 'id');
    }

    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    public function scopeActive($query)
    {
        return $query->whereNull('blocked_at');
    }

    public function scopeBlocked($query)
    {
        return $query->whereNotNull('blocked_at');
    }
}
