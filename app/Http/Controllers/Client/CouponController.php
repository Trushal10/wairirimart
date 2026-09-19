<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use App\Services\CouponService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CouponController extends Controller
{
    public function __construct(
        private CartService $cartService,
        private CouponService $couponService,
    ) {}

    public function apply(Request $request)
    {
        $request->validate(['code' => 'required|string|max:50']);

        $cart = $this->cartService->cartList();
        if (empty($cart)) {
            return response()->json(['error' => true, 'message' => 'Your cart is empty.'], 400);
        }

        $subtotal = collect($cart)->sum(fn ($item) => (float) $item['price'] * (int) $item['quantity']);
        $customerId = Auth::guard('customer')->id();

        try {
            $result = $this->couponService->apply($request->input('code'), $subtotal, $customerId);

            return response()->json([
                'success'  => true,
                'code'     => $result['code'],
                'type'     => $result['type'],
                'value'    => $result['value'],
                'discount' => $result['discount'],
                'message'  => 'Coupon applied! You save ₹' . number_format($result['discount'], 2),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()], 422);
        }
    }

    public function remove()
    {
        $this->couponService->remove();
        return response()->json(['success' => true, 'message' => 'Coupon removed.']);
    }
}
