<?php

namespace App\Http\Controllers\Client;

use App\Helper\CommonHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\ReviewRequest;
use App\Models\Order;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    public function getReviewsByProduct(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer|exists:products,id',
        ]);

        $reviews = Review::query()
            ->where('product_id', $request->product_id)
            ->where('is_approved', true)
            ->where('is_spam', false)
            ->latest('id')
            ->paginate(10);

        return response()->json($reviews);
    }

    public function store(ReviewRequest $request)
    {
        $customerId = Auth::guard('customer')->id();
        $productId = (int) $request->input('product_id');

        $hasPurchased = Order::query()
            ->where('customer_id', $customerId)
            ->whereIn('status', [Order::CONFIRMED, Order::COMPLETED])
            ->whereHas('orderItems', function ($q) use ($productId) {
                $q->where('product_id', $productId);
            })
            ->exists();

        if (! $hasPurchased) {
            return response()->json([
                'success' => false,
                'message' => 'You can review a product only after purchase.',
            ], 403);
        }

        $existing = Review::query()
            ->where('product_id', $productId)
            ->where('customer_id', $customerId)
            ->exists();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'You have already reviewed this product.',
            ], 422);
        }

        $inputs = $request->validated();
        $inputs['customer_id'] = $customerId;
        $inputs['is_approved'] = false;

        if ($request->file('image')) {
            $inputs['image'] = CommonHelper::uploadFile($request->file('image'), 'review');
        }

        $review = Review::query()->create($inputs);

        return response()->json([
            'success' => true,
            'message' => 'Thank you! Your review has been submitted and is awaiting approval.',
            'review' => $review,
        ]);
    }
}
