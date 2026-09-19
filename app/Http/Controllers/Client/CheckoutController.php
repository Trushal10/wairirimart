<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\CustomerAddress;
use App\Services\CartService;
use Illuminate\Support\Facades\Auth;

class CheckoutController extends Controller
{
    public function __construct(public CartService $cartService) {}

    public function index()
    {
        $cart = $this->cartService->cartList();

        if (empty($cart)) {
            return redirect()
                ->route('client.shoppingcart')
                ->with('info', 'Your cart is empty. Add some products before checkout.');
        }

        $addresses = [];
        if (Auth::guard('customer')->check()) {
            $customerId = Auth::guard('customer')->user()->id;
            $addresses = CustomerAddress::query()->where('customer_id', $customerId)->get();
        }

        return view('client.checkout', compact('cart', 'addresses'));
    }
}
