<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill Phase 1 of the variant model.
 *
 *  - Seeds two baseline attributes (Size, Color) so admin doesn't start empty.
 *  - Creates ONE default product_variant per existing product. The default variant
 *    inherits the product's canonical price/stock/sku/barcode/weight — this keeps
 *    every legacy read path working while giving new code a variant_id to reference.
 *  - Backfills order_items.product_variant_id + snapshots so historical orders
 *    can be rendered against a stable variant record even if the product's
 *    price/stock/sku changes later.
 *
 * This migration is idempotent: re-running it will not create duplicate defaults.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attributes') || ! Schema::hasTable('product_variants')) {
            // A prior migration failed / was rolled back — nothing to backfill against.
            return;
        }

        $this->seedBaselineAttributes();
        $this->createDefaultVariants();
        $this->backfillOrderItems();
    }

    public function down(): void
    {
        if (Schema::hasTable('order_items') && Schema::hasColumn('order_items', 'product_variant_id')) {
            DB::table('order_items')->update([
                'product_variant_id' => null,
                'variant_options' => null,
                'product_name_snapshot' => null,
                'variant_sku_snapshot' => null,
            ]);
        }

        if (Schema::hasTable('product_variants')) {
            // Only remove the auto-created defaults — variants with attached
            // product_variant_values are admin-authored and must be preserved.
            $autoIds = DB::table('product_variants')
                ->where('is_default', true)
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('product_variant_values')
                        ->whereColumn('product_variant_values.product_variant_id', 'product_variants.id');
                })
                ->pluck('id');

            if ($autoIds->isNotEmpty()) {
                DB::table('product_variants')->whereIn('id', $autoIds)->delete();
            }
        }

        if (Schema::hasTable('attributes')) {
            // Drop the seeded attributes only if nothing else references their values.
            foreach (['size', 'color'] as $code) {
                $attr = DB::table('attributes')->where('code', $code)->first();
                if (! $attr) {
                    continue;
                }
                $hasChildren = DB::table('attribute_values')->where('attribute_id', $attr->id)->exists();
                $inUse = DB::table('product_variant_values')->where('attribute_id', $attr->id)->exists();
                if (! $hasChildren && ! $inUse) {
                    DB::table('attributes')->where('id', $attr->id)->delete();
                }
            }
        }
    }

    private function seedBaselineAttributes(): void
    {
        $now = now();
        $baselines = [
            ['name' => 'Size',  'code' => 'size',  'swatch_type' => 'none',  'position' => 1],
            ['name' => 'Color', 'code' => 'color', 'swatch_type' => 'color', 'position' => 2],
        ];

        foreach ($baselines as $row) {
            $exists = DB::table('attributes')->where('code', $row['code'])->exists();
            if (! $exists) {
                DB::table('attributes')->insert(array_merge($row, [
                    'status'     => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }
    }

    private function createDefaultVariants(): void
    {
        $now = now();
        $productsWithoutDefault = DB::table('products as p')
            ->leftJoin('product_variants as v', function ($join) {
                $join->on('v.product_id', '=', 'p.id')->where('v.is_default', '=', true);
            })
            ->whereNull('v.id')
            ->whereNull('p.deleted_at')
            ->select('p.id', 'p.sku', 'p.stock', 'p.price', 'p.compere_price', 'p.barcode', 'p.weight')
            ->get();

        foreach ($productsWithoutDefault as $product) {
            DB::table('product_variants')->insert([
                'product_id'    => $product->id,
                // price/compare_price left NULL → variant inherits product-level pricing.
                'price'         => null,
                'compare_price' => null,
                'sku'           => $product->sku,
                'barcode'       => $product->barcode ?? null,
                'stock'         => (int) ($product->stock ?? 0),
                'weight'        => $product->weight ?? null,
                'image_url'     => null,
                'position'      => 0,
                'status'        => true,
                'is_default'    => true,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }
    }

    private function backfillOrderItems(): void
    {
        if (! Schema::hasColumn('order_items', 'product_variant_id')) {
            return;
        }

        // For every order_item that has a product but no variant, snap it to the
        // product's default variant and record a stable name/sku snapshot.
        $items = DB::table('order_items as oi')
            ->join('products as p', 'p.id', '=', 'oi.product_id')
            ->join('product_variants as v', function ($join) {
                $join->on('v.product_id', '=', 'p.id')->where('v.is_default', '=', true);
            })
            ->whereNull('oi.product_variant_id')
            ->select('oi.id', 'oi.size', 'oi.color', 'p.name as product_name', 'v.id as variant_id', 'v.sku as variant_sku')
            ->get();

        foreach ($items as $item) {
            $options = [];
            if (! empty($item->size))  { $options['Size']  = $item->size; }
            if (! empty($item->color)) { $options['Color'] = $item->color; }

            DB::table('order_items')->where('id', $item->id)->update([
                'product_variant_id'    => $item->variant_id,
                'variant_options'       => empty($options) ? null : json_encode($options),
                'product_name_snapshot' => $item->product_name,
                'variant_sku_snapshot'  => $item->variant_sku,
            ]);
        }
    }
};
