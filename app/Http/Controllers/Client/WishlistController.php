<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WishlistController extends Controller
{
    private function customerId(): int
    {
        return Auth::guard('customer')->id();
    }

    public function index()
    {
        $items = Wishlist::with([
            'product' => fn ($q) => $q->with(['medias' => fn ($m) => $m->where('type', ProductMedia::IMAGE)->limit(1)]),
            'variant',
        ])->where('customer_id', $this->customerId())->latest()->get();

        return view('client.wishlist', compact('items'));
    }

    /**
     * Toggle: add if absent, remove if present. Used by AJAX heart buttons.
     */
    public function toggle(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'variant_id' => 'nullable|integer|exists:product_variants,id',
        ]);

        $productId = (int) $request->input('product_id');
        $variantId = $request->input('variant_id') ? (int) $request->input('variant_id') : null;
        $customerId = $this->customerId();

        $existing = Wishlist::where('customer_id', $customerId)
            ->where('product_id', $productId)
            ->where('variant_id', $variantId)
            ->first();

        if ($existing) {
            $existing->delete();
            $wishlisted = false;
        } else {
            Wishlist::create([
                'customer_id' => $customerId,
                'product_id'  => $productId,
                'variant_id'  => $variantId,
            ]);
            $wishlisted = true;
        }

        $count = Wishlist::where('customer_id', $customerId)->count();

        return response()->json([
            'success'    => true,
            'wishlisted' => $wishlisted,
            'count'      => $count,
            'message'    => $wishlisted ? 'Added to wishlist.' : 'Removed from wishlist.',
        ]);
    }

    /**
     * Returns array of product IDs in the customer's wishlist.
     * Used to initialise heart-button state on page load.
     */
    public function ids()
    {
        if (! Auth::guard('customer')->check()) {
            return response()->json(['ids' => [], 'count' => 0]);
        }

        $ids = Wishlist::where('customer_id', $this->customerId())
            ->pluck('product_id')
            ->unique()
            ->values();

        return response()->json([
            'ids'   => $ids,
            'count' => $ids->count(),
        ]);
    }

    public function remove(Request $request)
    {
        $request->validate(['product_id' => 'required|integer']);

        Wishlist::where('customer_id', $this->customerId())
            ->where('product_id', (int) $request->input('product_id'))
            ->delete();

        return back()->with('success', 'Removed from wishlist.');
    }
}
