<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Universal search endpoint used by the admin Command Palette (⌘K).
     * Returns a small, structured payload of matching orders / products / categories.
     */
    public function __invoke(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '' || mb_strlen($q) < 2) {
            return response()->json(['orders' => [], 'products' => [], 'categories' => []]);
        }

        $like = '%'.$q.'%';

        $orders = Order::query()
            ->where(function ($w) use ($like) {
                $w->where('order_no', 'like', $like)
                    ->orWhere('shipping_name', 'like', $like)
                    ->orWhere('shipping_email', 'like', $like)
                    ->orWhere('shipping_phone', 'like', $like);
            })
            ->latest('id')
            ->limit(6)
            ->get(['id', 'order_no', 'shipping_name', 'shipping_email', 'total'])
            ->map(fn ($o) => [
                'id' => $o->id,
                'order_no' => $o->order_no,
                'customer_name' => $o->shipping_name,
                'total' => (float) $o->total,
                'href' => route('admin.order.detail', $o->id),
            ]);

        $products = Product::query()
            ->where(function ($w) use ($like) {
                $w->where('name', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhere('slug', 'like', $like);
            })
            ->latest('id')
            ->limit(6)
            ->get(['id', 'name', 'sku'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'href' => route('admin.product.edit', $p->id),
            ]);

        $categories = Category::query()
            ->where('name', 'like', $like)
            ->orWhere('slug', 'like', $like)
            ->orderBy('name')
            ->limit(6)
            ->get(['id', 'name'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'href' => route('admin.category.edit', $c->id),
            ]);

        return response()->json([
            'orders' => $orders,
            'products' => $products,
            'categories' => $categories,
        ]);
    }
}
