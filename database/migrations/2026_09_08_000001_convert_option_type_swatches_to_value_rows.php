<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;

/**
 * Rewrite products.option_types to the value-row shape.
 *
 * Before:
 *   [{ "name": "Colour", "type": "color", "swatches": { "Red": "#c62828" } }]
 *   — the value list itself lived only on the variants, so the admin could not
 *     order the pills, and a value no variant used yet had nowhere to live.
 *
 * After:
 *   [{ "name": "Colour", "type": "color",
 *      "values": [{ "value": "Red", "swatch": "#c62828" }] }]
 *
 * Values are recovered from the product's variants (including soft-deleted ones,
 * so a temporarily disabled combination keeps its swatch) and ordered by variant
 * position. Rows already in the new shape are left alone, which makes this safe
 * to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Product::query()
            ->whereNotNull('option_types')
            ->with(['variants' => fn ($q) => $q->withTrashed()->orderBy('position')])
            ->chunkById(100, function ($products) {
                foreach ($products as $product) {
                    $converted = $this->convert($product);
                    if ($converted !== null) {
                        $product->forceFill(['option_types' => $converted])->saveQuietly();
                    }
                }
            });
    }

    public function down(): void
    {
        Product::query()
            ->whereNotNull('option_types')
            ->chunkById(100, function ($products) {
                foreach ($products as $product) {
                    $types = is_array($product->option_types) ? $product->option_types : [];
                    $reverted = [];
                    $changed = false;

                    foreach ($types as $ot) {
                        $row = ['name' => $ot['name'] ?? '', 'type' => $ot['type'] ?? 'text'];
                        $swatches = [];
                        foreach ((array) ($ot['values'] ?? []) as $value) {
                            if (is_array($value) && ! empty($value['value']) && ! empty($value['swatch'])) {
                                $swatches[$value['value']] = $value['swatch'];
                            }
                        }
                        if (! empty($swatches)) {
                            $row['swatches'] = $swatches;
                        }
                        if (array_key_exists('values', $ot)) {
                            $changed = true;
                        }
                        $reverted[] = $row;
                    }

                    if ($changed) {
                        $product->forceFill(['option_types' => $reverted])->saveQuietly();
                    }
                }
            });
    }

    /** Null when every group is already in the new shape. */
    private function convert(Product $product): ?array
    {
        $types = is_array($product->option_types) ? $product->option_types : [];
        if (empty($types)) {
            return null;
        }

        $converted = [];
        $changed   = false;

        foreach ($types as $ot) {
            $name = trim((string) ($ot['name'] ?? ''));
            $type = in_array($ot['type'] ?? 'text', ['text', 'color', 'image'], true) ? $ot['type'] : 'text';

            // Already converted: a list of {value, swatch} rows.
            $existing = $ot['values'] ?? null;
            if (is_array($existing) && $existing !== [] && is_array(reset($existing))) {
                $converted[] = ['name' => $name, 'type' => $type, 'values' => array_values($existing)];
                continue;
            }

            $changed  = true;
            $swatches = is_array($ot['swatches'] ?? null) ? $ot['swatches'] : [];

            // Bare-string value list, else the values the variants actually use.
            $values = collect(is_array($existing) ? $existing : [])
                ->map(fn ($v) => trim((string) $v))
                ->filter()
                ->values();

            if ($values->isEmpty()) {
                $values = $product->variants
                    ->map(fn ($v) => is_array($v->options) ? ($v->options[$name] ?? null) : null)
                    ->filter(fn ($v) => $v !== null && $v !== '')
                    ->map(fn ($v) => (string) $v)
                    ->unique()
                    ->values();
            }

            // A swatch for a value no variant carries would otherwise be lost.
            foreach (array_keys($swatches) as $value) {
                if (! $values->contains((string) $value)) {
                    $values->push((string) $value);
                }
            }

            $converted[] = [
                'name'   => $name,
                'type'   => $type,
                'values' => $values
                    ->map(fn ($value) => [
                        'value'  => $value,
                        'swatch' => $type === 'text' ? null : ($swatches[$value] ?? null),
                    ])
                    ->values()
                    ->all(),
            ];
        }

        return $changed ? $converted : null;
    }
};
