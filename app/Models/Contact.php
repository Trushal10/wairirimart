<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    // Explicit allowlist — $guarded=[] previously allowed any column to be
    // mass-assigned from the public contact form.
    protected $fillable = [
        'name',
        'email',
        'phone',
        'city',
        'subject',
        'message',
    ];
}
