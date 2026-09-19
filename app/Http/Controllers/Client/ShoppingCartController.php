<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Http\Request;

class ShoppingCartController extends Controller
{
    public function __construct(public CartService $cartService) {}

    public function index()
    {
        $cart = $this->cartService->cartList();

        return view('client.shopping-cart', compact('cart'));
    }

    public function updateCheckOutItem(Request $request)
    {
        $inputs = $request->validate([
            'productId' => ['required', 'integer', 'min:1'],
            // Optional — present when the cart line belongs to a specific variant.
            'variantId' => ['nullable', 'integer', 'min:1'],
            'quantity'  => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $product = Product::query()->find($inputs['productId']);
        if (! $product) {
            return response()->json(['error' => true, 'message' => 'Product not found'], 404);
        }

        // Stock guard so the cart can't exceed available inventory. A variant id
        // only counts while the product still sells by variant — otherwise the
        // product's own stock column governs.
        $availableStock = (int) $product->stock;
        if (! empty($inputs['variantId']) && $product->has_variants) {
            $variant = \App\Models\ProductVariant::where('id', $inputs['variantId'])
                ->where('product_id', $product->id)
                ->where('status', true)
                ->first();
            if (! $variant) {
                return response()->json(['error' => true, 'message' => 'Variant not found'], 404);
            }
            $availableStock = (int) $variant->stock;
        }

        if ($availableStock < 1) {
            return response()->json([
                'error'   => true,
                'message' => 'This item is out of stock.',
            ], 412);
        }
        if ($inputs['quantity'] > $availableStock) {
            return response()->json([
                'error'   => true,
                'message' => "Only {$availableStock} available.",
                'cappedQuantity' => $availableStock,
            ], 412);
        }

        $result = $this->cartService->addToCart($product, $inputs);
        if (! $result) {
            return response()->json(['error' => true, 'message' => 'Could not update cart.'], 422);
        }

        $cart = $this->cartService->cartList();
        return response()->json([
            'success' => true,
            'cart'    => $cart,
        ]);
    }

    public function removeCheckOutItem(Request $request)
    {
        $inputs = $request->validate([
            'productId' => ['required', 'integer', 'min:1'],
            'variantId' => ['nullable', 'integer', 'min:1'],
            'cartKey'   => ['nullable', 'string', 'max:64'],
        ]);

        $result = $this->cartService->removeCartItem($inputs);
        if ($result) {
            $cart = $this->cartService->cartList();
            return response()->json([
                'success' => true,
                'message' => 'Item removed successfully',
                'cart'    => $cart,
            ]);
        }

        return response()->json(['error' => true, 'message' => 'Cart item not found'], 404);
    }
}
