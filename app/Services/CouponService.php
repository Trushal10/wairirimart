<?php

namespace App\Services;

use App\Models\Coupon;
use Illuminate\Support\Facades\Session;

class CouponService
{
    const SESSION_KEY = 'coupon';

    /**
     * Validate and store a coupon in session. Returns the coupon details.
     *
     * @throws \RuntimeException with a user-friendly message on failure
     */
    public function apply(string $code, float $subtotal, ?int $customerId = null): array
    {
        $coupon = Coupon::where('code', strtoupper(trim($code)))->first();

        if (! $coupon) {
            throw new \RuntimeException('Coupon code not found.');
        }

        if (! $coupon->isValid($customerId)) {
            throw new \RuntimeException('This coupon is expired, inactive, or not valid for your account.');
        }

        $discount = $coupon->calculateDiscount($subtotal);

        if ($discount <= 0) {
            throw new \RuntimeException(
                'Your order does not meet the minimum of ₹' . number_format((float) $coupon->min_order_amount, 2) . ' required for this coupon.'
            );
        }

        $data = [
            'id'       => $coupon->id,
            'code'     => $coupon->code,
            'type'     => $coupon->type,
            'value'    => (float) $coupon->value,
            'discount' => $discount,
        ];

        Session::put(self::SESSION_KEY, $data);

        return $data;
    }

    public function get(): ?array
    {
        return Session::get(self::SESSION_KEY);
    }

    /**
     * Recalculate discount against the current subtotal (call before order creation).
     */
    public function recalculate(float $subtotal, ?int $customerId = null): float
    {
        $stored = $this->get();
        if (! $stored) return 0.0;

        $coupon = Coupon::find($stored['id']);
        if (! $coupon || ! $coupon->isValid($customerId)) {
            Session::forget(self::SESSION_KEY);
            return 0.0;
        }

        $discount = $coupon->calculateDiscount($subtotal);
        Session::put(self::SESSION_KEY, array_merge($stored, ['discount' => $discount]));
        return $discount;
    }

    public function remove(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    /**
     * Mark the coupon as used. Call only after order is committed.
     */
    public function consume(): void
    {
        $stored = $this->get();
        if (! $stored) return;

        $coupon = Coupon::find($stored['id']);
        $coupon?->incrementUsage();
        Session::forget(self::SESSION_KEY);
    }

    /**
     * Hand a redemption back when an order is cancelled before confirmation.
     *
     * Works from the code stored on the order rather than the session, which
     * is long gone by the time anyone cancels — consume() is the checkout-time
     * counterpart and reads the session instead.
     */
    public function release(string $code): void
    {
        $coupon = Coupon::where('code', strtoupper(trim($code)))->first();
        $coupon?->decrementUsage();
    }
}
