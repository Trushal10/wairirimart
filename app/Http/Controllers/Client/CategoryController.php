<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductMedia;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $categorySlug = $request->get('category');
        $query = $request->get('query') ?? $request->get('text');

        $data['max_price'] = Product::query()->max('price');
        $data['categories'] = Category::query()
            ->select('name', 'slug', 'image')
            ->where('status', 1)
            ->withCount('productCategory')
            ->get();

        $data['products'] = Product::query()
            ->with([
                'medias' => function ($query) {
                    $query->select('product_id', 'url')->where('type', ProductMedia::IMAGE)->limit(2);
                }
            ])
            // SQL rollup of active-variant stock so the card can show real
            // availability for variant products (base products.stock is 0).
            ->withSum([
                'variants as variants_stock_sum' => fn ($q) => $q->where('status', true),
            ], 'stock')
            ->where('status', 1);

        $path = $request->route()->uri();
        if (!empty($categorySlug)) {
            $categoryId = Category::query()->where('slug', $categorySlug)->value('id');
            $data['products']->whereHas('categories', function ($query) use ($categoryId) {
                $query->where('category_id', $categoryId);
            });
            $path .= '?category=' . $categorySlug;
        }
        if (!empty($query)) {
            $data['products']->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                    ->orWhere('price', 'LIKE', "%{$query}%");
            });
            // keep query param in pagination path
            $path .= (!str_contains($path, '?') ? '?' : '&') . 'query=' . urlencode($query);
        }

        $data['products'] = $data['products']
            ->paginate(12)
            ->withPath($path)
            ->toArray();

        return view('client.category', compact('data'));
    }
}
