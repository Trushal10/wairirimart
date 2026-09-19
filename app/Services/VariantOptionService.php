<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Builds the storefront-facing variant payload for a product.
 *
 * The product detail page and the quick-add modal need exactly the same two
 * structures, and they used to build them with two near-identical copies of the
 * same code — which is how the modal ended up without the out-of-stock handling
 * the detail page had. This is the single source of truth.
 *
 * variants: [
 *   { id, sku, price, compare_price, stock, is_default, image_url,
 *     options: { "Colour": "Red" } },
 * ]
 *
 * groups: [
 *   { name: "Colour", type: "color", values: [
 *       { value: "Red", label: "Red", swatch: "#c62828", in_stock: true },
 *   ]},
 * ]
 *
 * `type` mirrors products.option_types[].type and drives which pill the blade
 * renders (text button / colour dot / image thumb). `swatch` is already
 * resolved to a public URL for image swatches so the view never has to know
 * where variant media lives.
 */
class VariantOptionService
{
    public const TYPES = ['text', 'color', 'image'];

    /**
     * @return array{variants: array, groups: array}
     */
    public function payload(Product $product): array
    {
        if (! $product->has_variants) {
            return ['variants' => [], 'groups' => []];
        }

        if (! $product->relationLoaded('variants')) {
            $product->load(['variants' => fn ($q) => $q->where('status', true)->orderBy('position')]);
        }

        $variants = $this->variants($product);

        return [
            'variants' => $variants,
            'groups'   => $this->groups($product, $variants),
        ];
    }

    /**
     * The variant the page opens on: the admin's default while it is in stock,
     * otherwise the first variant that can actually be bought, otherwise the
     * default again so a fully sold-out product still shows a coherent price
     * next to its "Out of stock" label.
     */
    public function displayVariant(array $variants): ?array
    {
        if (empty($variants)) {
            return null;
        }

        $default = collect($variants)->firstWhere('is_default', true) ?? $variants[0];
        if ((int) ($default['stock'] ?? 0) > 0) {
            return $default;
        }

        return collect($variants)->first(fn ($v) => (int) ($v['stock'] ?? 0) > 0) ?? $default;
    }

    /**
     * Active variants flattened for JSON, with product-level price fallback
     * already applied so the front-end never has to re-derive it.
     */
    private function variants(Product $product): array
    {
        return $product->variants
            ->where('status', true)
            ->map(fn ($v) => [
                'id'            => (int) $v->id,
                'sku'           => $v->sku,
                'price'         => $v->price !== null ? (float) $v->price : (float) $product->price,
                'compare_price' => $v->compare_price !== null ? (float) $v->compare_price : (float) $product->compere_price,
                'stock'         => (int) $v->stock,
                'is_default'    => (bool) $v->is_default,
                'image_url'     => $v->image_url ? asset('storage/product/' . $v->image_url) : null,
                'options'       => is_array($v->options) ? $v->options : [],
            ])
            ->values()
            ->all();
    }

    /**
     * Option groups in the admin-declared order.
     *
     * Values come from the admin's option_types definition when present (that is
     * what carries the swatches); anything the definition omits but a variant
     * actually uses is appended so a partially-migrated product still renders a
     * complete picker. Values no variant uses are dropped — they would be dead
     * pills that can never resolve to a SKU.
     */
    private function groups(Product $product, array $variants): array
    {
        $declared = is_array($product->option_types) ? $product->option_types : [];

        if (empty($declared)) {
            $declared = collect($variants)
                ->flatMap(fn ($v) => array_keys($v['options'] ?? []))
                ->unique()
                ->map(fn ($name) => ['name' => $name, 'type' => 'text', 'values' => []])
                ->values()
                ->all();
        }

        $groups = [];

        foreach ($declared as $ot) {
            $name = trim((string) ($ot['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $type = in_array($ot['type'] ?? 'text', self::TYPES, true) ? $ot['type'] : 'text';

            // Distinct values actually carried by a variant, in variant order.
            $used = collect($variants)
                ->map(fn ($v) => $v['options'][$name] ?? null)
                ->filter(fn ($v) => $v !== null && $v !== '')
                ->unique()
                ->values();

            if ($used->isEmpty()) {
                continue;
            }

            $swatches = $this->swatchMap($ot, $type);

            $values = $this->orderedValues($ot, $used)
                ->map(fn ($value) => [
                    'value'    => $value,
                    'label'    => $value,
                    // For an image group, fall back to the image of a variant
                    // carrying this value when the admin uploaded no dedicated
                    // swatch. Otherwise a product whose variants all have images
                    // still renders a row of bare text pills with a thumbnail on
                    // whichever single value happened to get an explicit upload,
                    // which reads as broken rather than as "not configured yet".
                    'swatch'   => $swatches[$value]
                        ?? ($type === 'image' ? $this->variantImageFor($variants, $name, $value) : null),
                    // Coarse availability for the server-rendered initial state.
                    // The picker narrows this per-selection once JS boots.
                    'in_stock' => $this->hasStockFor($variants, $name, $value),
                ])
                ->values()
                ->all();

            $groups[] = ['name' => $name, 'type' => $type, 'values' => $values];
        }

        return $groups;
    }

    /**
     * value => resolved swatch. Hex passes through untouched; image filenames
     * become public URLs. Text groups never carry a swatch.
     */
    private function swatchMap(array $optionType, string $type): array
    {
        if ($type === 'text') {
            return [];
        }

        $map = [];
        foreach ($this->valueRows($optionType) as $row) {
            $value  = trim((string) ($row['value'] ?? ''));
            $swatch = $row['swatch'] ?? null;
            if ($value === '' || empty($swatch)) {
                continue;
            }
            $map[$value] = $type === 'image'
                ? asset('storage/product/' . $swatch)
                : (string) $swatch;
        }

        return $map;
    }

    /**
     * Normalize the stored value list to [{value, swatch}] rows.
     *
     * Three shapes have existed on this column:
     *   current  values: [{value, swatch}]
     *   legacy A swatches: {value: swatch}   — value list lived only on the variants
     *   legacy B values: ["Red", "Blue"]     — bare strings, no swatches at all
     * A migration rewrites both legacy shapes, but reading them here keeps any
     * row the migration has not reached rendering with its swatches intact.
     */
    private function valueRows(array $optionType): array
    {
        $rows = [];

        foreach ((array) ($optionType['values'] ?? []) as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            } elseif (is_string($row) && trim($row) !== '') {
                $rows[] = ['value' => trim($row), 'swatch' => null];
            }
        }

        if (empty($rows) && is_array($optionType['swatches'] ?? null)) {
            foreach ($optionType['swatches'] as $value => $swatch) {
                $rows[] = ['value' => (string) $value, 'swatch' => $swatch];
            }
        }

        return $rows;
    }

    /**
     * Admin-declared display order first, then any variant-only leftovers.
     */
    private function orderedValues(array $optionType, Collection $used): Collection
    {
        $declaredOrder = collect($this->valueRows($optionType))
            ->map(fn ($row) => trim((string) ($row['value'] ?? '')))
            ->filter()
            ->values();

        if ($declaredOrder->isEmpty()) {
            return $used;
        }

        return $declaredOrder
            ->filter(fn ($value) => $used->contains($value))
            ->concat($used->reject(fn ($value) => $declaredOrder->contains($value)))
            ->unique()
            ->values();
    }

    /**
     * Image of the first variant carrying $name = $value. Already an absolute
     * URL — variants() resolved it. Null when no such variant has an image.
     */
    private function variantImageFor(array $variants, string $name, string $value): ?string
    {
        foreach ($variants as $variant) {
            if (($variant['options'][$name] ?? null) === $value && ! empty($variant['image_url'])) {
                return $variant['image_url'];
            }
        }

        return null;
    }

    private function hasStockFor(array $variants, string $name, string $value): bool
    {
        foreach ($variants as $variant) {
            if (($variant['options'][$name] ?? null) === $value && (int) $variant['stock'] > 0) {
                return true;
            }
        }

        return false;
    }
}
