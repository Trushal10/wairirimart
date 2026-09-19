<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductMedia extends Model
{
    protected $table = 'product_medias';

    const IMAGE = 'image';
    const VIDEO = 'video';

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'url',
        'alt_text',
        'is_primary',
        'type',
        'priority',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'priority'   => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
