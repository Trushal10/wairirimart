<?php

namespace App\Http\Controllers\Client;

use App\Models\Review;
use App\Models\Slider;
use App\Models\Product;
use App\Models\Category;
use App\Models\HomeVideo;
use App\Models\ProductMedia;
use App\Http\Controllers\Controller;

class HomeController extends Controller
{
    public function index()
    {
        $data['categories'] = Category::query()->where('status', 1)->withCount('productCategory')->get();
        $data['sliders'] = Slider::query()->where('status', 1)->with('sliderMedias')->get();
        $data['videos'] = HomeVideo::query()->active()->ordered()->limit(12)->get();
        // has_variants is required so the product-card blade can decide whether
        // to read the SQL rollup (variants_stock_sum) vs the base stock column.
        $listSelect = ['id', 'name', 'slug', 'compere_price', 'price', 'stock', 'has_variants'];
        $variantStockRollup = [
            'variants as variants_stock_sum' => fn ($q) => $q->where('status', true),
        ];

        // "Top picks" and "trending" render the same set of featured products.
        // This used to run the identical query — same columns, same rollup, same
        // eager loads, same filters — twice per request, doubling the homepage's
        // database work for an identical result.
        $featuredProducts = Product::query()
            ->select($listSelect)
            ->withSum($variantStockRollup, 'stock')
            ->with([
                'medias' => function ($query) {
                    $query->select('url', 'product_id')->where('type', ProductMedia::IMAGE)->orderBy('priority')->limit(2);
                },
            ])
            ->where('status', 1)
            ->where('featured', 1)
            ->get();

        $data['highlighted_products'] = $featuredProducts;
        $data['trendy_products']      = $featuredProducts;
        $data['reviews'] = Review::query()
            // ->where('status', 1)
            ->orderBy('created_at', 'desc')
            ->limit(6)
            ->get();

        return view('client.index', compact('data'));
    }
}
