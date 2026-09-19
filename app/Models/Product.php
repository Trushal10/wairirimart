<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'short_description',
        'description',
        'return_policy',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'canonical_url',
        'og_image',
        'brand',
        'tax_class',
        'hs_code',
        'barcode',
        'weight',
        'sizes',
        'color',
        'price',
        'compere_price',
        'cost_price',
        'stock',
        'sku',
        'status',
        'featured',
        'has_variants',
        'show_quantity',
        'option_types',
    ];

    protected $casts = [
        'featured'      => 'boolean',
        'status'        => 'boolean',
        'has_variants'  => 'boolean',
        'show_quantity' => 'boolean',
        'price'         => 'decimal:2',
        'compere_price' => 'decimal:2',
        'cost_price'    => 'decimal:2',
        'weight'        => 'decimal:3',
        'option_types'  => 'array',
    ];

    public function medias(): HasMany
    {
        return $this->hasMany(ProductMedia::class, 'product_id', 'id')->orderBy('priority');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(ProductCategory::class, 'product_id', 'id');
    }

    public function getCategoryDetails(): HasManyThrough
    {
        return $this->hasManyThrough(Category::class, ProductCategory::class, 'product_id', 'id', 'id', 'category_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'product_id', 'id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('position');
    }

    public function activeVariants(): HasMany
    {
        return $this->variants()->where('status', true);
    }

    public function defaultVariant()
    {
        return $this->hasOne(ProductVariant::class)->where('is_default', true);
    }

    /**
     * Total addressable stock across variants when `has_variants=true`,
     * otherwise the product-level stock. Falls back to product-level stock
     * if variants are not loaded to keep legacy read paths working.
     */
    public function getEffectiveStockAttribute(): int
    {
        if ($this->has_variants && $this->relationLoaded('variants')) {
            return (int) $this->variants->where('status', true)->sum('stock');
        }
        return (int) ($this->attributes['stock'] ?? 0);
    }

    /**
     * Lowest price across active variants when `has_variants=true`,
     * otherwise the product-level price. Used for list/card display.
     */
    public function getStartingPriceAttribute(): float
    {
        if ($this->has_variants && $this->relationLoaded('variants')) {
            $prices = $this->variants
                ->where('status', true)
                ->map(fn ($v) => $v->price !== null ? (float) $v->price : (float) $this->price);
            if ($prices->isNotEmpty()) {
                return (float) $prices->min();
            }
        }
        return (float) ($this->attributes['price'] ?? 0);
    }

    /**
     * Legacy stock decrement kept for backward compatibility with pre-variant
     * checkout flows. New code should decrement on ProductVariant instead.
     */
    public function decrementStock(int $qyt): void
    {
        if ($this->stock >= $qyt) {
            $this->stock -= $qyt;
            $this->save();
        } else {
            throw new \Exception("Limited stock alert! Only {$this->stock} items available for {$this->name}.");
        }
    }
}
