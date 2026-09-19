<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\Review;
use App\Services\VariantOptionService;

class ProductController extends Controller
{
    public function __construct(private VariantOptionService $variantOptions) {}

    public function index($productSlug = '')
    {
        if (empty($productSlug)) {
            return view('client.404');
        }

        $product = Product::query()
            ->where('slug', $productSlug)
            ->with([
                'medias',
                'getCategoryDetails:categories.id,categories.name',
                'variants' => fn ($q) => $q->where('status', true)->orderBy('position'),
            ])
            ->first();

        if (empty($product)) {
            return view('client.404');
        }

        // Variants + option groups (text / colour / image swatches) for the
        // picker, shaped by the same service the quick-add modal uses so the two
        // pickers can never disagree.
        ['variants' => $variants, 'groups' => $attributeGroups] = $this->variantOptions->payload($product);
        $displayVariant = $this->variantOptions->displayVariant($variants);

        $categoryIds = $product['getCategoryDetails']->pluck('id');
        $data['related_products'] = Product::query()
            ->whereHas('categories', function ($query) use ($categoryIds, $product) {
                $query->whereIn('category_id', $categoryIds)->whereNot('product_id', $product->id);
            })
            ->with([
                'medias' => function ($query) {
                    $query->select('url', 'product_id')->where('type', ProductMedia::IMAGE)->limit(2);
                },
            ])
            // Rollup so product-card can show correct availability.
            ->withSum([
                'variants as variants_stock_sum' => fn ($q) => $q->where('status', true),
            ], 'stock')
            ->limit(4)->get();

        $data['reviews'] = Review::where('product_id', $product->id)
            ->where('is_approved', true)
            ->orderBy('created_at', 'desc')
            ->get();

        $totalReviews = $data['reviews']->count();
        $ratingCounts = [
            5 => $data['reviews']->where('rate', 5)->count(),
            4 => $data['reviews']->where('rate', 4)->count(),
            3 => $data['reviews']->where('rate', 3)->count(),
            2 => $data['reviews']->where('rate', 2)->count(),
            1 => $data['reviews']->where('rate', 1)->count(),
        ];
        $averageRating = $totalReviews > 0 ? number_format($data['reviews']->avg('rate'), 1) : 0;
        $ratingPercent = [];
        foreach ($ratingCounts as $star => $count) {
            $ratingPercent[$star] = $totalReviews > 0 ? ($count / $totalReviews) * 100 : 0;
        }

        $data['totalReviews']    = $totalReviews;
        $data['ratingCounts']    = $ratingCounts;
        $data['averageRating']   = $averageRating;
        $data['ratingPercent']   = $ratingPercent;
        $data['variants']        = $variants;
        $data['attributeGroups'] = $attributeGroups;
        $data['displayVariant']  = $displayVariant;

        return view('client.product', compact('product', 'data'));
    }
}
