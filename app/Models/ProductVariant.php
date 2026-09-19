<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'sku',
        'barcode',
        'price',
        'compare_price',
        'cost_price',
        'stock',
        'weight',
        'image_url',
        'position',
        'status',
        'is_default',
        'options',
    ];

    protected $casts = [
        'product_id'    => 'integer',
        'price'         => 'decimal:2',
        'compare_price' => 'decimal:2',
        'cost_price'    => 'decimal:2',
        'stock'         => 'integer',
        'weight'        => 'decimal:3',
        'position'      => 'integer',
        'status'        => 'boolean',
        'is_default'    => 'boolean',
        'options'       => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(ProductVariantValue::class);
    }

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(
            AttributeValue::class,
            'product_variant_values',
            'product_variant_id',
            'attribute_value_id'
        );
    }

    public function medias(): HasMany
    {
        return $this->hasMany(ProductMedia::class, 'product_variant_id')->orderBy('priority');
    }

    /**
     * Effective price = variant.price when set, else falls back to product.price.
     * Callers should eager-load 'product' to avoid an N+1.
     */
    public function getEffectivePriceAttribute(): float
    {
        if ($this->price !== null) {
            return (float) $this->price;
        }
        return (float) ($this->product?->price ?? 0);
    }

    public function getEffectiveComparePriceAttribute(): float
    {
        if ($this->compare_price !== null) {
            return (float) $this->compare_price;
        }
        return (float) ($this->product?->compere_price ?? 0);
    }

    /**
     * Optimistic in-memory + DB stock decrement. Callers MUST wrap in a
     * transaction and use lockForUpdate() when concurrent orders are possible.
     */
    public function decrementStock(int $qty): void
    {
        if ($qty < 1) {
            throw new \InvalidArgumentException('Quantity must be at least 1');
        }
        if ($this->stock < $qty) {
            throw new \RuntimeException("Only {$this->stock} units available");
        }
        $this->stock -= $qty;
        $this->save();
    }

    public function incrementStock(int $qty): void
    {
        if ($qty < 1) {
            return;
        }
        $this->stock += $qty;
        $this->save();
    }

    /**
     * Human-readable "Variety: 1 kg / Color: Red" label from the options JSON.
     * Returns empty string for variants with no options (single-SKU products).
     */
    public function getOptionLabelAttribute(): string
    {
        if (empty($this->options) || ! is_array($this->options)) {
            return '';
        }
        $parts = [];
        foreach ($this->options as $name => $value) {
            $parts[] = $name . ': ' . $value;
        }
        return implode(' / ', $parts);
    }
}
