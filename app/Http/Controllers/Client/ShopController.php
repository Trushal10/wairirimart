<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductMedia;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index(Request $request)
    {
        $categorySlug = $request->get('category');
        $searchTerm = trim((string) $request->get('q', ''));

        $data['max_price'] = Product::query()->max('price');
        $data['categories'] = Category::query()
            ->select('name', 'slug')
            ->where('status', 1)
            ->withCount('productCategory')
            ->get();
        $data['products'] = Product::query()
            ->with(['medias' => function ($query) {
                $query->select('product_id', 'url')->where('type', ProductMedia::IMAGE)->limit(2);
            }])
            // SQL rollup of active-variant stock so product cards show real
            // availability for variant products (base products.stock is 0).
            ->withSum([
                'variants as variants_stock_sum' => fn ($q) => $q->where('status', true),
            ], 'stock')
            ->where('status', 1);

        $queryParams = [];
        if (! empty($categorySlug)) {
            $categoryId = Category::query()->where('slug', $categorySlug)->value('id');
            $data['products']->whereHas('categories', function ($query) use ($categoryId) {
                $query->where('category_id', $categoryId);
            });
            $queryParams['category'] = $categorySlug;
        }

        if ($searchTerm !== '') {
            $data['products']->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', '%'.$searchTerm.'%')
                  ->orWhere('brand', 'like', '%'.$searchTerm.'%')
                  ->orWhere('description', 'like', '%'.$searchTerm.'%');
            });
            $queryParams['q'] = $searchTerm;
        }

        $data['search'] = $searchTerm;
        $data['products'] = $data['products']
            ->paginate(12)
            ->appends($queryParams)
            ->toArray();

        return view('client.shop', compact('data'));
    }
}
