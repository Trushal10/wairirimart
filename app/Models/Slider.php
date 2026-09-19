<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Slider extends Model
{
    protected $guarded = [];

    public function sliderMedias(): HasMany
    {
        return $this->hasMany(SliderMedia::class, 'slider_id', 'id')->orderBy('priority');
    }
}
