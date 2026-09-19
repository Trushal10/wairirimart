<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Session;

/**
 * Session-backed shopping cart.
 *
 * Cart storage schema:
 *   session('cart') = [
 *     "<product_id>:<variant_id|0>" => [
 *        'id'         => product_id,
 *        'variant_id' => variant_id|null,
 *        'quantity'   => int,
 *        'unit_price' => float,   // snapshot at add-to-cart time
 *        'size'       => string|null,  // legacy or variant snapshot
 *        'color'      => string|null,
 *        'options'    => ['Size' => 'M', 'Color' => 'Red'],  // variant snapshot
 *     ],
 *   ]
 *
 * Legacy carts (keyed by bare product_id) are migrated transparently on read.
 */
class CartService
{
    public function cartList(): array
    {
        $cart = $this->normalizeCart(Session::get('cart', []));
        if (empty($cart)) {
            return [];
        }

        $productIds = collect($cart)->pluck('id')->unique()->all();
        $variantIds = collect($cart)->pluck('variant_id')->filter()->unique()->all();

        $products = Product::query()
            ->with(['medias' => function ($q) {
                $q->select('product_id', 'url')->where('type', ProductMedia::IMAGE)->limit(1);
            }])
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $variants = collect();
        if (! empty($variantIds)) {
            $variants = ProductVariant::query()
                ->whereIn('id', $variantIds)
                ->get()
                ->keyBy('id');
        }

        $data = [];
        foreach ($cart as $key => $line) {
            $product = $products[$line['id']] ?? null;
            if (! $product) {
                continue;
            }
            $variant = ! empty($line['variant_id']) && $product->has_variants
                ? ($variants[$line['variant_id']] ?? null)
                : null;

            $unitPrice = $line['unit_price'] ?? (
                $variant && $variant->price !== null ? (float) $variant->price : (float) $product->price
            );
            $stock = $variant ? (int) $variant->stock : (int) $product->stock;
            $image = $variant?->image_url
                ?? ($product->medias->first()->url ?? null);

            // Flat "Weight: 1 kg / Colour: Red" string. The mini-cart drawer
            // renders from a flat {placeholder} template and cannot walk the
            // options dict itself, and without it two variants of the same
            // product show up as two identical rows.
            $lineOptions = is_array($line['options'] ?? null) ? $line['options'] : [];
            if (empty($lineOptions)) {
                if (! empty($line['color'])) {
                    $lineOptions['Color'] = $line['color'];
                }
                if (! empty($line['size'])) {
                    $lineOptions['Size'] = $line['size'];
                }
            }
            $lineOptions = $this->orderByDeclaredOptions($lineOptions, $product);
            $optionLabel = implode(' / ', array_map(
                fn ($value, $name) => $name . ': ' . $value,
                $lineOptions,
                array_keys($lineOptions),
            ));

            $data[] = [
                'id'         => $product->id,
                'variant_id' => $variant?->id,
                'name'       => $product->name,
                'slug'       => $product->slug,
                'price'      => $unitPrice,
                'unit_price' => $unitPrice,
                'stock'      => $stock,
                'quantity'   => (int) ($line['quantity'] ?? 1),
                'color'      => $line['color'] ?? null,
                'size'       => $line['size'] ?? null,
                'options'    => $lineOptions,
                'option_label' => $optionLabel,
                'sku'        => $variant?->sku ?? $product->sku,
                'image'      => $image,
                'names'      => $line['names'] ?? new \stdClass(),
                'cart_key'   => (string) $key,
            ];
        }

        return $data;
    }

    /**
     * Add a product (optionally a specific variant) to the cart. Enforces stock
     * caps at the variant level when a variant is present.
     */
    public function addToCart(Product $product, array $inputs): bool
    {
        $requestedQty = (int) ($inputs['quantity'] ?? 1);
        if ($requestedQty < 1) {
            return false;
        }

        // A variant id is only honoured while the product actually sells by
        // variant. Once an admin turns variants off the product is priced and
        // stocked by its own columns, so a stale id from an old tab or an old
        // session is dropped rather than resurrecting variant pricing.
        $variantId = ! empty($inputs['variantId']) && $product->has_variants
            ? (int) $inputs['variantId']
            : null;
        $variant   = null;
        $unitPrice = (float) $product->price;
        $availableStock = (int) $product->stock;
        $options = [];

        if ($variantId) {
            $variant = ProductVariant::query()
                ->where('id', $variantId)
                ->where('product_id', $product->id)
                ->where('status', true)
                ->first();
            if (! $variant) {
                return false;
            }
            $unitPrice = $variant->price !== null ? (float) $variant->price : (float) $product->price;
            $availableStock = (int) $variant->stock;
            // Snapshot the variant's per-product option selections (Variety / Weight / …).
            if (is_array($variant->options)) {
                $options = $variant->options;
            }
        }

        if ($availableStock < 1) {
            return false;
        }

        $qty = min($requestedQty, $availableStock);
        $cart = $this->normalizeCart(Session::get('cart', []));
        $key = $this->cartKey($product->id, $variantId);

        $existing = $cart[$key] ?? null;
        $cart[$key] = [
            'id'         => (int) $product->id,
            'variant_id' => $variantId,
            'quantity'   => $qty,
            'unit_price' => $unitPrice,
            'size'       => $inputs['size'] ?? ($existing['size'] ?? null),
            'color'      => $inputs['color'] ?? ($existing['color'] ?? null),
            'options'    => $options ?: ($existing['options'] ?? []),
            'names'      => $inputs['names'] ?? ($existing['names'] ?? []),
        ];

        Session::put('cart', $cart);

        return true;
    }

    public function removeCartItem(array $inputs): bool
    {
        $cart = $this->normalizeCart(Session::get('cart', []));

        // Accept either an explicit cart_key or a product+variant pair.
        $key = $inputs['cartKey']
            ?? $this->cartKey(
                (int) ($inputs['productId'] ?? 0),
                ! empty($inputs['variantId']) ? (int) $inputs['variantId'] : null,
            );

        if (isset($cart[$key])) {
            unset($cart[$key]);
            Session::put('cart', $cart);
            return true;
        }

        // Legacy fallback: session may still be keyed by bare product_id.
        $legacyKey = (string) ($inputs['productId'] ?? '');
        if ($legacyKey !== '' && isset($cart[$legacyKey])) {
            unset($cart[$legacyKey]);
            Session::put('cart', $cart);
            return true;
        }

        return false;
    }

    /**
     * Re-order a variant's options to match the product's declared option_types.
     *
     * MySQL normalises JSON object keys on write, so a variant saved as
     * {Weight, Colour, Finish} reads back as {Colour, Finish, Weight}. The
     * storefront picker renders in the admin's declared order, so the cart has
     * to as well or the same variant reads differently in the two places.
     * Anything not declared (legacy color/size) is appended in its existing order.
     */
    private function orderByDeclaredOptions(array $options, Product $product): array
    {
        $declared = is_array($product->option_types) ? $product->option_types : [];
        if (empty($declared) || empty($options)) {
            return $options;
        }

        $ordered = [];
        foreach ($declared as $type) {
            $name = $type['name'] ?? null;
            if ($name !== null && array_key_exists($name, $options)) {
                $ordered[$name] = $options[$name];
            }
        }

        return $ordered + $options;
    }

    /**
     * Migrate a legacy cart (keyed by bare product_id) to the new
     * "product:variant" key format on the fly.
     */
    private function normalizeCart(array $cart): array
    {
        $normalized = [];
        foreach ($cart as $key => $line) {
            if (is_array($line) && isset($line['id'])) {
                $productId = (int) $line['id'];
                $variantId = ! empty($line['variant_id']) ? (int) $line['variant_id'] : null;
                $normalized[$this->cartKey($productId, $variantId)] = $line;
            }
        }
        return $normalized;
    }

    private function cartKey(int $productId, ?int $variantId): string
    {
        return $productId . ':' . ($variantId ?: 0);
    }
}
