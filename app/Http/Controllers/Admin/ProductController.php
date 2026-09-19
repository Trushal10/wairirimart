<?php

namespace App\Http\Controllers\Admin;

use App\Helper\CommonHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $status = $request->input('status');

        $query = Product::query()
            ->withCount('variants')
            // SQL rollup of active-variant stock so the Products listing shows the
            // real total even though the base products.stock column is 0 for
            // variant-based products. Aliased instead of using withSum's default
            // key so we can read a stable field name on the Vue side.
            ->withSum([
                'variants as variants_stock_sum' => fn ($q) => $q->where('status', true),
            ], 'stock');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($status === 'active') {
            $query->where('status', 1);
        } elseif ($status === 'inactive') {
            $query->where('status', 0);
        }

        $products = $query->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Product/Index', [
            'products' => $products,
            'filters'  => ['search' => $search, 'status' => $status],
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Product/Create', [
            'categories' => $this->categoriesForForm(),
        ]);
    }

    public function store(ProductRequest $request)
    {
        $inputs = $request->validated();

        return DB::transaction(function () use ($inputs) {
            [$productPayload, $categories, $gallery, $variants, $optionTypes] = $this->splitPayload($inputs);


            $product = Product::create($productPayload);
            $this->syncCategories($product, $categories);
            $this->syncGallery($product, $gallery);
            $this->syncVariants($product, $variants, $optionTypes, (bool) $productPayload['has_variants']);

            return redirect()->route('admin.products')
                ->with('success', 'Product created successfully');
        });
    }

    public function edit(Product $product)
    {
        $product->load([
            'medias' => fn ($q) => $q->orderBy('priority'),
            'variants' => fn ($q) => $q->orderBy('position'),
        ]);

        $product->setAttribute('categories', $product->categories()->pluck('category_id')->toArray());
        $product->setAttribute('variants_payload', $product->variants->map(function ($v) {
            return [
                'id'            => $v->id,
                'sku'           => $v->sku,
                'barcode'       => $v->barcode,
                'price'         => $v->price !== null ? (float) $v->price : null,
                'compare_price' => $v->compare_price !== null ? (float) $v->compare_price : null,
                'stock'         => (int) $v->stock,
                'weight'        => $v->weight !== null ? (float) $v->weight : null,
                'image_url'     => $v->image_url,
                'position'      => (int) $v->position,
                'status'        => (bool) $v->status,
                'is_default'    => (bool) $v->is_default,
                // Dict of { "Variety": "1 kg" }
                'options'       => is_array($v->options) ? $v->options : [],
            ];
        })->values());

        return Inertia::render('Admin/Product/Create', [
            'product'    => $product,
            'categories' => $this->categoriesForForm(),
        ]);
    }

    public function update(ProductRequest $request, Product $product)
    {
        $inputs = $request->validated();

        return DB::transaction(function () use ($inputs, $product) {
            [$productPayload, $categories, $gallery, $variants, $optionTypes] = $this->splitPayload($inputs, $product);


            $product->update($productPayload);
            $this->syncCategories($product, $categories);
            $this->syncGallery($product, $gallery, keepExisting: true);
            $this->syncVariants($product, $variants, $optionTypes, (bool) $productPayload['has_variants']);

            return redirect()->route('admin.products')
                ->with('success', 'Product updated successfully');
        });
    }

    public function delete(Product $product)
    {
        DB::transaction(function () use ($product) {
            foreach ($product->medias()->get() as $media) {
                CommonHelper::removeOldFile('public/product/' . $media->url);
            }
            // Same prune path as the variants toggle, so a variant image is never
            // unlinked while a historical order line still renders from it.
            $this->pruneVariants($product, []);
            foreach ($this->imageSwatchFiles($product->option_types) as $file) {
                CommonHelper::removeOldFile('public/product/' . $file);
            }
            $product->delete();
        });

        return redirect()->back()->with('success', 'Product deleted successfully');
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * Split the validated payload into (productPayload, categories, gallery, variants, optionTypes)
     * and normalize the legacy sizes/color JSON plus the option_types JSON on the product row.
     *
     * $existing is the product being updated, so swatch images it no longer
     * references can be removed from disk.
     */
    private function splitPayload(array $inputs, ?Product $existing = null): array
    {
        $categories  = $inputs['categories'] ?? [];
        $gallery     = $inputs['gallery'] ?? [];
        $variants    = $inputs['variants'] ?? [];
        $optionTypes = $inputs['option_types'] ?? [];

        unset($inputs['categories'], $inputs['gallery'], $inputs['variants'], $inputs['option_types']);

        // `has_variants` comes straight from the admin's toggle (normalized in
        // ProductRequest::validated) — never inferred from the variant array.
        // Inferring it meant the toggle could be off while stale rows were still
        // posted, leaving a single-SKU product flagged as a variant product.
        $hasVariants = ! empty($inputs['has_variants']);

        // Legacy JSON columns — only written when the product is a single SKU.
        // A variant product's options live in product_variants.options.
        $inputs['sizes']         = (! $hasVariants && ! empty($inputs['sizes'])) ? json_encode(array_values($inputs['sizes'])) : null;
        $inputs['color']         = (! $hasVariants && ! empty($inputs['color'])) ? json_encode(array_map('trim', explode(',', $inputs['color']))) : null;
        $inputs['description']   = CommonHelper::sanitizeHtml($inputs['description'] ?? null);
        $inputs['return_policy'] = CommonHelper::sanitizeHtml($inputs['return_policy'] ?? null);

        // Option groups only mean anything alongside variants. With the toggle
        // off they are dropped and their swatch images cleaned up.
        $optionTypes = $this->normalizeOptionTypes($hasVariants ? $optionTypes : [], $existing);
        $inputs['option_types'] = ! empty($optionTypes) ? $optionTypes : null;

        return [$inputs, $categories, $gallery, ($hasVariants ? $variants : []), $optionTypes];
    }

    /**
     * Normalize the per-product option group definitions.
     *
     * Shape persisted to products.option_types:
     *   [{ name: "Colour", type: "color", values: [
     *        { value: "Red",  swatch: "#c62828" },
     *        { value: "Blue", swatch: "#1565c0" },
     *   ]}]
     *
     * `swatch` is a hex colour for type=color, an uploaded filename (relative to
     * storage/product) for type=image, and always null for type=text. Storing the
     * value list here — rather than deriving it from whatever the variants happen
     * to carry — is what lets the admin control the pill order and attach a
     * swatch to a value, which is why the colour and image option types never
     * rendered as anything but text before.
     */
    private function normalizeOptionTypes(array $optionTypes, ?Product $existing): array
    {
        $previousImages = $this->imageSwatchFiles($existing?->option_types);
        $keptImages     = [];
        $clean          = [];

        foreach ($optionTypes as $ot) {
            $name = trim((string) ($ot['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $type = $ot['type'] ?? 'text';
            if (! in_array($type, ['text', 'color', 'image'], true)) {
                $type = 'text';
            }

            $values = [];
            $seen   = [];
            foreach ((array) ($ot['values'] ?? []) as $row) {
                $value = trim((string) ($row['value'] ?? ''));
                if ($value === '' || isset($seen[$value])) {
                    continue;
                }
                $seen[$value] = true;

                $swatch = null;
                if ($type === 'image') {
                    if (! empty($row['swatch_file'])) {
                        $swatch = CommonHelper::uploadFile($row['swatch_file'], 'product');
                    } elseif (! empty($row['swatch']) && in_array($row['swatch'], $previousImages, true)) {
                        // A filename can only be kept, never typed in: it has to
                        // be one this product already owns.
                        $swatch = (string) $row['swatch'];
                    }
                    if ($swatch !== null) {
                        $keptImages[] = $swatch;
                    }
                } elseif ($type === 'color') {
                    $candidate = trim((string) ($row['swatch'] ?? ''));
                    // Accept #rgb / #rrggbb only; anything else is dropped so the
                    // storefront never injects an unvetted string into a style attr.
                    $swatch = preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $candidate) === 1
                        ? strtolower($candidate)
                        : null;
                }

                $values[] = ['value' => $value, 'swatch' => $swatch];
            }

            $clean[] = ['name' => $name, 'type' => $type, 'values' => $values];
        }

        foreach (array_diff($previousImages, $keptImages) as $file) {
            CommonHelper::removeOldFile('public/product/' . $file);
        }

        return $clean;
    }

    /** Filenames of every image swatch stored in an option_types JSON blob. */
    private function imageSwatchFiles($optionTypes): array
    {
        $files = [];

        foreach ((is_array($optionTypes) ? $optionTypes : []) as $ot) {
            if (($ot['type'] ?? 'text') !== 'image') {
                continue;
            }
            // Current shape is a values list; the pre-migration shape was a
            // value => filename map. Read both so an update can still clean up
            // after a product the migration has not reached.
            foreach ((array) ($ot['values'] ?? []) as $row) {
                $file = is_array($row) ? ($row['swatch'] ?? null) : null;
                if (is_string($file) && $file !== '') {
                    $files[] = $file;
                }
            }
            foreach ((array) ($ot['swatches'] ?? []) as $file) {
                if (is_string($file) && $file !== '') {
                    $files[] = $file;
                }
            }
        }

        return array_values(array_unique($files));
    }

    private function syncCategories(Product $product, array $categoryIds): void
    {
        ProductCategory::where('product_id', $product->id)->delete();
        if (empty($categoryIds)) {
            return;
        }
        $now = now();
        $rows = array_map(fn ($id) => [
            'product_id'  => $product->id,
            'category_id' => (int) $id,
            'created_at'  => $now,
            'updated_at'  => $now,
        ], array_values($categoryIds));
        ProductCategory::insert($rows);
    }

    /**
     * Sync product-level gallery. When keepExisting=true (update flow) any existing
     * medias not present in the incoming URL list are removed from disk + db.
     */
    private function syncGallery(Product $product, array $gallery, bool $keepExisting = false): void
    {
        if ($keepExisting) {
            $requestUrls = collect($gallery)->pluck('url')->filter()->all();
            foreach ($product->medias()->whereNull('product_variant_id')->get() as $media) {
                if (! in_array($media->url, $requestUrls, true)) {
                    CommonHelper::removeOldFile('public/product/' . $media->url);
                    $media->delete();
                }
            }
        }

        foreach ($gallery as $index => $item) {
            if (empty($item['file'])) {
                continue;
            }
            $url = CommonHelper::uploadFile($item['file'], 'product');
            ProductMedia::create([
                'product_id' => $product->id,
                'url'        => $url,
                'alt_text'   => $item['alt'] ?? null,
                'is_primary' => ! empty($item['primary']),
                'priority'   => $index,
                'type'       => ProductMedia::IMAGE,
            ]);
        }
    }

    /**
     * Upsert product_variants using the new JSON `options` dict.
     *
     * - Rows in $variants with an id  → update.
     * - Rows in $variants without id  → create.
     * - Existing variants NOT in the payload → soft-deleted.
     * - Exactly one row ends up is_default=true, and it is always an active
     *   row: the flagged one while it is active, else the first active row.
     *   A default nobody can buy would open the product page on "Out of stock".
     * - Only options that appear in the product's option_types definitions are
     *   persisted (defensive — prevents stray keys from client tampering).
     * - A variant image can be replaced (upload) or cleared (empty image_url),
     *   never renamed by hand; the file that is dropped is removed from disk.
     *
     * When $hasVariants is false the whole matrix is pruned: every variant row
     * is soft-deleted so the product falls back to its base stock/price/SKU.
     * Leaving them behind would keep the stock rollups (admin listing, product
     * cards) reporting phantom variant inventory, and would leave the orphaned
     * variant ids addressable from the cart.
     */
    private function syncVariants(Product $product, array $variants, array $optionTypes, bool $hasVariants): void
    {
        if (! $hasVariants || empty($variants)) {
            $this->pruneVariants($product, []);

            return;
        }

        $allowedNames = array_map(
            fn ($ot) => trim((string) ($ot['name'] ?? '')),
            $optionTypes
        );
        $allowedNames = array_filter($allowedNames);

        $keepIds = [];

        foreach ($variants as $index => $row) {
            // Sanitize options — only keep keys that match declared option_types names.
            $optionsIn = $row['options'] ?? [];
            $options = [];
            foreach ($optionsIn as $name => $value) {
                $name  = trim((string) $name);
                $value = trim((string) $value);
                if ($value === '' || $name === '') {
                    continue;
                }
                if (! empty($allowedNames) && ! in_array($name, $allowedNames, true)) {
                    continue;
                }
                $options[$name] = $value;
            }

            $payload = [
                'product_id'    => $product->id,
                'sku'           => $row['sku'] ?? null,
                'barcode'       => $row['barcode'] ?? null,
                'price'         => isset($row['price']) && $row['price'] !== '' ? (float) $row['price'] : null,
                'compare_price' => isset($row['compare_price']) && $row['compare_price'] !== '' ? (float) $row['compare_price'] : null,
                'stock'         => (int) ($row['stock'] ?? 0),
                'weight'        => isset($row['weight']) && $row['weight'] !== '' ? (float) $row['weight'] : null,
                'position'      => (int) ($row['position'] ?? $index),
                // Missing key defaults to active; otherwise use filter_var so "0"/"false"/0/false
                // all resolve to false. This is more predictable than mixing empty() with isset(),
                // which have well-known surprises around "0" and null.
                'status'        => array_key_exists('status', $row) ? filter_var($row['status'], FILTER_VALIDATE_BOOLEAN) : true,
                'is_default'    => filter_var($row['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'options'       => $options,
            ];

            // The form has no null in multipart data: "" is "image removed".
            $clearImage = array_key_exists('image_url', $row)
                && ($row['image_url'] === null || $row['image_url'] === '');
            $newImage = ! empty($row['image_file'])
                ? CommonHelper::uploadFile($row['image_file'], 'product')
                : null;

            if (! empty($row['id'])) {
                $variant = ProductVariant::where('product_id', $product->id)
                    ->where('id', (int) $row['id'])
                    ->first();
                if ($variant) {
                    if ($newImage !== null) {
                        $payload['image_url'] = $newImage;
                    } elseif ($clearImage) {
                        $payload['image_url'] = null;
                    }
                    // Otherwise the stored filename stays as it is.
                    if ($variant->image_url
                        && array_key_exists('image_url', $payload)
                        && $payload['image_url'] !== $variant->image_url) {
                        CommonHelper::removeOldFile('public/product/' . $variant->image_url);
                    }
                    $variant->update($payload);
                    $keepIds[] = $variant->id;
                    continue;
                }
            }

            $payload['image_url'] = $newImage;
            $variant = ProductVariant::create($payload);
            $keepIds[] = $variant->id;
        }

        if (! empty($keepIds)) {
            $rows = ProductVariant::whereIn('id', $keepIds)->orderBy('position')->get();
            $default = $rows->first(fn ($v) => $v->is_default && $v->status)
                ?? $rows->first(fn ($v) => $v->status)
                ?? $rows->first();
            ProductVariant::whereIn('id', $keepIds)
                ->where('id', '!=', $default->id)
                ->update(['is_default' => false]);
            if (! $default->is_default) {
                ProductVariant::whereKey($default->id)->update(['is_default' => true]);
            }
        }

        $this->pruneVariants($product, $keepIds);
    }

    /**
     * Soft-delete every variant of $product except those in $keepIds.
     *
     * The variant image is only unlinked when no order item references the
     * variant — historical orders render their line image from the variant, and
     * a soft-deleted row with a dangling image path shows a broken thumbnail in
     * the admin order detail and on the customer's invoice.
     */
    private function pruneVariants(Product $product, array $keepIds): void
    {
        $query = $product->variants();
        if (! empty($keepIds)) {
            $query->whereNotIn('id', $keepIds);
        }

        $doomed = $query->get();
        if ($doomed->isEmpty()) {
            return;
        }

        $referenced = OrderItem::whereIn('product_variant_id', $doomed->pluck('id'))
            ->pluck('product_variant_id')
            ->unique()
            ->flip();

        foreach ($doomed as $variant) {
            if ($variant->image_url && ! $referenced->has($variant->id)) {
                CommonHelper::removeOldFile('public/product/' . $variant->image_url);
            }
            $variant->delete();
        }
    }

    private function categoriesForForm(): array
    {
        return Category::query()
            ->where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($c) => ['id' => $c->id, 'label' => $c->name])
            ->toArray();
    }
}
