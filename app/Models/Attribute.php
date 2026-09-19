<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attribute extends Model
{
    const SWATCH_NONE  = 'none';
    const SWATCH_COLOR = 'color';
    const SWATCH_IMAGE = 'image';

    protected $fillable = [
        'name',
        'code',
        'swatch_type',
        'position',
        'status',
    ];

    protected $casts = [
        'position' => 'integer',
        'status'   => 'boolean',
    ];

    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class)->orderBy('position');
    }

    public function activeValues(): HasMany
    {
        return $this->values()->where('status', true);
    }
}
