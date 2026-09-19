<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'product_variant_id',
        'quantity',
        'size',
        'color',
        'variant_options',
        'product_name_snapshot',
        'variant_sku_snapshot',
        'price',
        'customization',
    ];

    protected $casts = [
        'quantity'         => 'integer',
        'price'            => 'decimal:2',
        'customization'    => 'array',
        'variant_options'  => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function media(): HasOne
    {
        return $this->hasOne(ProductMedia::class, 'product_id', 'product_id')->orderBy('priority');
    }

    /**
     * Human-readable option snapshot. Prefers the stored variant_options JSON
     * (persistent across product renames) and falls back to legacy size/color.
     */
    public function getOptionsLabelAttribute(): string
    {
        if (! empty($this->variant_options) && is_array($this->variant_options)) {
            return collect($this->variant_options)
                ->map(fn ($v, $k) => "{$k}: {$v}")
                ->implode(' / ');
        }
        $parts = [];
        if (! empty($this->size))  { $parts[] = 'Size: '  . $this->size; }
        if (! empty($this->color)) { $parts[] = 'Color: ' . $this->color; }
        return implode(' / ', $parts);
    }
}
