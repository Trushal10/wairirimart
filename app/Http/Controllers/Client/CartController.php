<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\AddToCartRequest;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Services\CartService;
use App\Services\VariantOptionService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(public CartService $cartService) {}

    public function cartList()
    {
        $data = $this->cartService->cartList();

        return response()->json([
            'cart'  => $data,
            'count' => count($data),
        ], 200);
    }

    public function addToCart(AddToCartRequest $request)
    {
        $inputs = $request->validated();

        $product = Product::query()->find($inputs['productId']);
        if (empty($product) || ! $product->status) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        $qty = (int) ($inputs['quantity'] ?? 1);

        // If a variant was picked, validate it belongs to this product, that the
        // product still sells by variant, and that it is in stock. Otherwise fall
        // back to product-level stock. The has_variants check matters for stale
        // tabs and old carts: once an admin turns variants off the product is
        // priced and stocked by its own columns, so an old variant id must not
        // be honoured.
        if (! empty($inputs['variantId']) && $product->has_variants) {
            $variant = ProductVariant::where('id', $inputs['variantId'])
                ->where('product_id', $product->id)
                ->where('status', true)
                ->first();
            if (! $variant) {
                return response()->json([
                    'error'   => true,
                    'message' => 'Selected variant is unavailable.',
                ], 404);
            }
            if ($variant->stock < 1) {
                return response()->json([
                    'error'   => true,
                    'message' => 'This variant is out of stock.',
                ], 412);
            }
            if ($variant->stock < $qty) {
                return response()->json([
                    'error'   => true,
                    'message' => "Only {$variant->stock} available for the selected option.",
                ], 412);
            }
        } else {
            if ($product->has_variants) {
                return response()->json([
                    'error'   => true,
                    'message' => 'Please choose an option before adding to cart.',
                ], 422);
            }
            if ($product->stock < 1) {
                return response()->json(['error' => true, 'message' => 'Product is out of stock.'], 412);
            }
            if ($product->stock < $qty) {
                return response()->json(['error' => true, 'message' => "Only {$product->stock} available."], 412);
            }
        }

        $this->cartService->addToCart($product, $inputs);
        $cart = $this->cartService->cartList();

        return response()->json(['success' => true, 'cart' => $cart], 200);
    }

    public function quickAdd(Request $request)
    {
        $inputs = $request->validate([
            'productId' => ['required', 'numeric', 'min:1'],
        ]);

        $product = Product::query()
            ->where('id', $inputs['productId'])
            ->with([
                'medias' => function ($query) {
                    $query->select('url', 'product_id')->where('type', ProductMedia::IMAGE)->orderBy('priority')->limit(1);
                },
                'variants' => fn ($q) => $q->where('status', true)->orderBy('position'),
            ])
            ->first();

        if (! $product) {
            return response('Product not found', 404);
        }

        // Same payload shape as the product page so the modal can resolve a
        // variantId from the shopper's selection with the shared picker script.
        $service = app(VariantOptionService::class);
        ['variants' => $variants, 'groups' => $attributeGroups] = $service->payload($product);

        return view('client.quick', [
            'product'         => $product,
            'variants'        => $variants,
            'attributeGroups' => $attributeGroups,
            'displayVariant'  => $service->displayVariant($variants),
        ])->render();
    }

    public function removeCartItem(Request $request)
    {
        $inputs = $request->validate([
            'productId' => ['required', 'numeric', 'min:1'],
            'variantId' => ['nullable', 'integer', 'min:1'],
            'cartKey'   => ['nullable', 'string', 'max:64'],
        ]);

        $result = $this->cartService->removeCartItem($inputs);
        if ($result) {
            $cart = $this->cartService->cartList();
            return response()->json(['success' => true, 'message' => 'Item removed successfully', 'cart' => $cart], 200);
        }

        return response()->json(['error' => 'Cart item not found'], 404);
    }
}
