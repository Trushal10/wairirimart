<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordReset extends Model
{
    protected $fillable = [
        'email',
        'otp',
        'token',
    ];

    protected $hidden = [
        'otp',
        'token',
    ];
}
