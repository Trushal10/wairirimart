<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SliderMedia extends Model
{
    protected $guarded = [];

    public const IMAGE = 'image';

    public const VIDEO = 'video';

    public const TYPES = [
        self::IMAGE => 'Image',
        self::VIDEO => 'Video',
    ];
}
