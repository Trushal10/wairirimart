<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\OrderRequest;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Notifications\OrderPlaced;
use App\Notifications\OrderStatusChanged;
use App\Services\CartService;
use App\Services\CouponService;
use App\Services\Payment\CodGateway;
use App\Services\Payment\PaymentGatewayManager;
use App\Services\Payment\PaymentReconciler;
use App\Services\SmsService;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class OrderController extends Controller
{
    public function __construct(
        public CartService            $cartService,
        public CouponService          $couponService,
        public PaymentGatewayManager  $gatewayManager,
        public PaymentReconciler      $reconciler,
    ) {}

    /**
     * Client-side callback URL for a given gateway code. Falls back to the
     * generic '/payment/callback' route when a gateway-specific route
     * doesn't exist yet (Stripe / PayPal roll-out).
     */
    private function callbackUrlForGateway(string $code): ?string
    {
        return match ($code) {
            'razorpay' => route('razorpay.callback'),
            default    => null,
        };
    }

    /**
     * Deterministic fingerprint of the cart contents (product + variant + qty).
     * Used to detect "same cart" retries — if the customer clicks Place Order
     * twice for the same cart within the reuse window we hand them back the
     * existing pending Razorpay/Stripe/PayPal order instead of creating a new
     * one (which would double-charge them once they complete both payments).
     */
    private function cartSignature(\Illuminate\Support\Collection $cart): string
    {
        $rows = $cart->map(fn ($line) => sprintf(
            '%d:%s:%d',
            (int) ($line['id'] ?? 0),
            ! empty($line['variant_id']) ? (int) $line['variant_id'] : 'p',
            (int) ($line['quantity'] ?? 0),
        ))->sort()->values()->all();
        return sha1(implode('|', $rows));
    }

    /**
     * Look for a pending online-payment order created by the same customer
     * within the retry window (default 20 min) with an identical cart + total.
     * Returns the reusable (Order, Payment) pair or null when none exists.
     *
     * Rationale: without this check, the flow "customer paid on Razorpay →
     * callback crashed → customer clicked Place Order again → new local order
     * created → customer paid AGAIN" charges them twice for the same intent.
     *
     * @return array{order: Order, payment: Payment}|null
     */
    private function findReusablePendingOrder(
        int $customerId,
        string $paymentMethod,
        float $total,
        string $cartSignature,
        int $windowMinutes = 20,
    ): ?array {
        $payment = Payment::query()
            ->where('type', $paymentMethod)
            ->where('status', Payment::STATUS_PENDING)
            ->where('created_at', '>=', now()->subMinutes($windowMinutes))
            ->whereHas('order', fn ($q) => $q->where('customer_id', $customerId)
                                            ->where('status', Order::PENDING))
            ->orderByDesc('id')
            ->get()
            ->first(function (Payment $p) use ($total, $cartSignature) {
                if (abs((float) $p->amount - $total) > 0.005) {
                    return false;
                }
                $storedSig = (string) (((array) $p->meta)['cart_signature'] ?? '');
                return $storedSig !== '' && hash_equals($storedSig, $cartSignature);
            });

        if (! $payment) {
            return null;
        }
        $order = Order::find($payment->order_id);
        if (! $order) {
            return null;
        }
        return ['order' => $order, 'payment' => $payment];
    }


    /**
     * Build the same normalized checkout response shape used for a fresh order,
     * but from an already-existing pending Payment row (used when
     * findReusablePendingOrder() returns a match on retry).
     */
    private function respondFromExistingPayment(Order $order, Payment $payment, string $paymentMethod)
    {
        try {
            $gateway = $this->gatewayManager->forCode($paymentMethod);
        } catch (ModelNotFoundException) {
            return response()->json([
                'error' => true,
                'message' => ucfirst($paymentMethod) . ' is not enabled.',
            ], 422);
        }

        // For Razorpay we don't need to hit the API again — everything the
        // frontend needs is already on the payment row + gateway config.
        $creds = (array) $gateway->getModel()->credentials;
        $callbackUrl = $this->callbackUrlForGateway($gateway->code());

        $response = [
            'success'          => true,
            'reused'           => true,
            'payment_method'   => $gateway->code(),
            'gateway'          => $gateway->code(),
            'order_id'         => $order->id,
            'order_no'         => $order->order_no,
            'gateway_order_id' => $payment->payment_id,
            'amount'           => (int) round(((float) $payment->amount) * 100),
            'currency'         => 'INR',
            'callback_url'     => $callbackUrl,
            'message'          => 'Resuming your earlier payment session.',
        ];

        if ($gateway->code() === 'razorpay') {
            $response['sdk_config'] = [
                'key'         => $creds['key_id'] ?? config('services.razorpay.key'),
                'order_id'    => $payment->payment_id,
                'name'        => config('app.name'),
                'description' => 'Order #' . $order->order_no,
            ];
            $response['razorpay_order_id'] = $payment->payment_id;
            $response['razorpay_key']      = $creds['key_id'] ?? config('services.razorpay.key');
        }

        return response()->json($response, 200);
    }

    public function save(OrderRequest $request)
    {
        $customerId = Auth::guard('customer')->id();
        if (empty($customerId)) {
            return response()->json(['error' => true, 'message' => 'Please login to place an order.'], 401);
        }

        $idempotencyKey = 'order_lock_' . $customerId;
        if (Session::has($idempotencyKey) && (time() - Session::get($idempotencyKey)) < 5) {
            return response()->json(['error' => true, 'message' => 'Duplicate request. Please wait.'], 429);
        }
        Session::put($idempotencyKey, time());

        $inputs = $request->validated();
        $addressId = $inputs['address_id'];
        $paymentMethod = $inputs['payment_method'] ?? 'cod';

        try {
            DB::beginTransaction();

            $address = CustomerAddress::where('id', $addressId)
                ->where('customer_id', $customerId)
                ->first();
            if (empty($address)) {
                DB::rollBack();
                return response()->json(['error' => true, 'message' => 'Invalid delivery address.'], 403);
            }

            $cart = collect(Session::get('cart', []));
            if ($cart->isEmpty()) {
                DB::rollBack();
                return response()->json(['error' => true, 'message' => 'Add at least one product.'], 400);
            }

            // Distinct id sets for locking.
            $productIds = $cart->pluck('id')->unique()->values()->all();
            $variantIds = $cart->pluck('variant_id')->filter()->unique()->values()->all();

            $products = Product::whereIn('id', $productIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $variants = collect();
            if (! empty($variantIds)) {
                $variants = ProductVariant::whereIn('id', $variantIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');
            }

            if ($products->count() !== count($productIds)) {
                DB::rollBack();
                return response()->json(['error' => true, 'message' => 'One or more products are no longer available.'], 400);
            }

            // Validate every cart line — product+variant existence, status, stock.
            foreach ($cart as $line) {
                $product = $products[$line['id']] ?? null;
                if (! $product || ! $product->status) {
                    DB::rollBack();
                    return response()->json(['error' => true, 'message' => "One of the items in your cart is no longer available."], 400);
                }
                $qty = (int) ($line['quantity'] ?? 0);
                if ($qty < 1) {
                    DB::rollBack();
                    return response()->json(['error' => true, 'message' => "Invalid quantity for {$product->name}."], 422);
                }
                if (! empty($line['variant_id']) && $product->has_variants) {
                    $variant = $variants[$line['variant_id']] ?? null;
                    if (! $variant || $variant->product_id !== $product->id || ! $variant->status) {
                        DB::rollBack();
                        return response()->json(['error' => true, 'message' => "Selected variant for {$product->name} is unavailable."], 400);
                    }
                    if ($variant->stock < $qty) {
                        DB::rollBack();
                        return response()->json(['error' => true, 'message' => "Only {$variant->stock} available for the selected {$product->name} variant."], 412);
                    }
                } else {
                    if ($product->has_variants) {
                        DB::rollBack();
                        return response()->json(['error' => true, 'message' => "{$product->name} requires an option selection."], 422);
                    }
                    if ($product->stock < $qty) {
                        DB::rollBack();
                        return response()->json(['error' => true, 'message' => "Only {$product->stock} available for {$product->name}."], 412);
                    }
                }
            }

            // Compute totals server-side from Product/Variant rows only.
            $subTotal = 0;
            $orderItems = [];
            foreach ($cart as $line) {
                $product = $products[$line['id']];
                // Same has_variants gate as the validation loop above, so the
                // price we charge and the row we snapshot always agree with what
                // was just checked.
                $variant = ! empty($line['variant_id']) && $product->has_variants
                    ? ($variants[$line['variant_id']] ?? null)
                    : null;

                $qty       = (int) $line['quantity'];
                $unitPrice = $variant && $variant->price !== null
                    ? (float) $variant->price
                    : (float) $product->price;

                $subTotal += $qty * $unitPrice;

                $orderItems[] = [
                    'product_id'            => $product->id,
                    'product_variant_id'    => $variant?->id,
                    'quantity'              => $qty,
                    'size'                  => $line['size'] ?? null,
                    'color'                 => $line['color'] ?? null,
                    'variant_options'       => ! empty($line['options']) ? json_encode($line['options']) : null,
                    'product_name_snapshot' => $product->name,
                    'variant_sku_snapshot'  => $variant?->sku ?? $product->sku,
                    'price'                 => $unitPrice,
                ];
            }

            $shipping = 0;
            $discount = $this->couponService->recalculate($subTotal, $customerId);
            $couponData = $this->couponService->get();
            $total = $subTotal + $shipping - $discount;

            // Duplicate-charge guard: for online gateways, look for an existing
            // pending payment with the same cart + amount from this customer in
            // the last 20 minutes. If found, hand the client the same gateway
            // checkout data so a retry completes the ONE payment instead of
            // creating a second Razorpay/Stripe/PayPal order.
            $cartSignature = $this->cartSignature($cart);
            if ($paymentMethod !== 'cod') {
                $reusable = $this->findReusablePendingOrder(
                    (int) $customerId,
                    $paymentMethod,
                    (float) $total,
                    $cartSignature,
                );
                if ($reusable !== null) {
                    DB::commit();
                    return $this->respondFromExistingPayment(
                        $reusable['order'],
                        $reusable['payment'],
                        $paymentMethod,
                    );
                }
            }

            $lastOrder = Order::latest('id')->first();
            $nextOrderNumber = Carbon::now()->timezone('Asia/Kolkata')->format('dmYHis') . (($lastOrder->id ?? 0) + 1);

            $order = Order::create([
                'order_no'          => $nextOrderNumber,
                'customer_id'       => $customerId,
                'status'            => Order::PENDING,
                'discount'          => $discount,
                'shipping'          => $shipping,
                'coupan_code'       => $couponData['code'] ?? null,
                'shipping_name'     => $address->name,
                'shipping_email'    => $address->email,
                'shipping_phone'    => $address->phone,
                'shipping_city'     => $address->city,
                'shipping_pincode'  => $address->pincode,
                'shipping_state'    => $address->state,
                'shipping_address'  => $address->address,
                'sub_total'         => $subTotal,
                'total'             => $total,
            ]);
            $order->orderItems()->createMany($orderItems);

            // Resolve the chosen gateway from the admin PaymentGateway table.
            // Every gateway (Razorpay, Stripe, PayPal, COD) implements the same
            // createCheckout() contract, so the whole block below is now
            // gateway-agnostic — adding a new provider is a matter of enabling
            // the row in admin + adding a client-side dispatch branch.
            try {
                $gateway = $this->gatewayManager->forCode($paymentMethod);
            } catch (ModelNotFoundException) {
                DB::rollBack();
                return response()->json([
                    'error'   => true,
                    'message' => ucfirst($paymentMethod) . ' is not enabled. Please choose a different payment method.',
                ], 422);
            }

            // Create the Payment shell first so createCheckout() can attach
            // the gateway order/intent id back onto it. Stamp the cart
            // signature onto meta so a retry within the reuse window can find
            // this row and hand the customer the same checkout instead of
            // creating a duplicate.
            $payment = Payment::create([
                'order_id'   => $order->id,
                'type'       => $gateway->code(),
                'payment_id' => null,
                'status'     => Payment::STATUS_PENDING,
                'amount'     => $order->total,
                'meta'       => ['cart_signature' => $cartSignature],
            ]);

            try {
                $checkout = $gateway->createCheckout($order, $payment);
            } catch (\RuntimeException $e) {
                // Gateway-specific "credentials missing" / "COD max amount"
                // messages — surface them to the customer verbatim.
                DB::rollBack();
                Log::warning($gateway->code() . ' checkout setup failed: ' . $e->getMessage(), [
                    'order_id'    => $order->id,
                    'customer_id' => $customerId,
                ]);
                return response()->json([
                    'error'   => true,
                    'message' => $e->getMessage() ?: 'Payment gateway is not configured. Please contact support.',
                ], 503);
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error($gateway->code() . ' createCheckout failed: ' . $e->getMessage(), [
                    'order_id'    => $order->id,
                    'customer_id' => $customerId,
                    'trace'       => $e->getTraceAsString(),
                ]);
                return response()->json([
                    'error'   => true,
                    'message' => 'Could not start payment. Please try again.',
                ], 502);
            }

            // COD is settled here — no external redirect, no callback. Money is
            // collected on delivery, but stock and coupon must move now so the
            // customer's cart clears and inventory can't be double-sold before
            // the courier arrives.
            $isCod = $gateway instanceof CodGateway;
            if ($isCod) {
                $this->reconciler->decrementStockForOrder($order);
            }

            DB::commit();

            if ($isCod) {
                // Post-commit side effects: coupon, cart clear, notifications.
                $this->couponService->consume();
                Session::forget('cart');

                try {
                    $customer = Customer::find($customerId);
                    if ($customer) {
                        $customer->notify(new OrderPlaced($order));
                        if ($order->shipping_phone) {
                            app(SmsService::class)->send(
                                $order->shipping_phone,
                                "Hi {$customer->name}, your order #{$order->order_no} has been placed. Total: Rs " . number_format((float) $order->total, 2) . ". - " . config('app.name')
                            );
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('OrderPlaced notification failed: ' . $e->getMessage());
                }

                return response()->json([
                    'success'        => true,
                    'payment_method' => $gateway->code(),
                    'gateway'        => $gateway->code(),
                    'order_id'       => $order->id,
                    'order_no'       => $order->order_no,
                    'message'        => 'Order placed successfully',
                    'redirectUrl'    => route('client.order.confirmation', ['orderNo' => $order->order_no]),
                ], 200);
            }

            // Online payment path — return a normalized response the client can
            // hand to the appropriate SDK. `sdk_config` is provider-specific
            // (Razorpay: key + order_id; Stripe: publishable_key + client_secret;
            // PayPal: approve_url; etc.). Backwards-compat fields (razorpay_*)
            // are kept alongside for the existing checkout JS.
            $callbackUrl = $this->callbackUrlForGateway($gateway->code());
            $response = [
                'success'          => true,
                'payment_method'   => $gateway->code(),
                'gateway'          => $gateway->code(),
                'order_id'         => $order->id,
                'order_no'         => $order->order_no,
                'gateway_order_id' => $checkout['gateway_order_id'] ?? null,
                'amount'           => $checkout['amount'] ?? (int) round($order->total * 100),
                'currency'         => $checkout['currency'] ?? 'INR',
                'sdk_config'       => $checkout['sdk_config'] ?? null,
                'callback_url'     => $callbackUrl,
                'message'          => 'Order created successfully',
            ];
            if ($gateway->code() === 'razorpay') {
                // Backwards-compat aliases for the existing checkout.blade.php JS.
                $response['razorpay_order_id'] = $checkout['gateway_order_id'] ?? null;
                $response['razorpay_key']      = $checkout['sdk_config']['key'] ?? null;
            }
            return response()->json($response, 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Sale Order Exception: ' . $e->getMessage(), [
                'customer_id' => $customerId,
                'trace'       => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error'   => true,
                'message' => 'Something went wrong. Refresh and try again',
            ], 500);
        }
    }

    /**
     * Thank-you page shown after an order is placed (both COD and Razorpay).
     * Ownership-guarded: only the customer who placed the order can view.
     */
    public function confirmation(string $orderNo)
    {
        $customerId = Auth::guard('customer')->id();
        if (empty($customerId)) {
            return redirect()->route('client.login')->with('info', 'Please sign in to view your order.');
        }

        // media: the page shows what was ordered, and a row of names with no
        // pictures is the one place a shopper cannot tell a 6-inch mould from
        // a 4-inch one. latestShipment: the milestone rail needs to know
        // whether the courier has it yet.
        $order = Order::with([
                'orderItems.product:id,name,slug',
                'orderItems.media' => fn ($q) => $q
                    ->select('product_id', 'url')
                    ->where('type', ProductMedia::IMAGE)
                    ->limit(1),
                'payment',
                'latestShipment',
            ])
            ->where('order_no', $orderNo)
            ->where('customer_id', $customerId)
            ->first();

        if (! $order) {
            return redirect()->route('client.home')->with('error', 'Order not found.');
        }

        return view('client.order-confirmation', compact('order'));
    }

    /**
     * Let a customer cancel their own order until it ships (see
     * Order::isCancellableByCustomer). A paid online order is flagged "refund
     * due" on its history for staff to refund from the admin order page.
     *
     * Until now the only way out of an unwanted order was to contact support
     * and have an admin flip the status — and even then the stock never came
     * back, because nothing mirrored decrementStockForOrder().
     *
     * The order row is locked and its status re-checked inside the
     * transaction: a prepaid order can be confirmed by a Razorpay webhook at
     * any moment, and whichever of the two gets the lock first must win
     * cleanly rather than both writing. PaymentReconciler::confirm() holds the
     * other side of that bargain and refuses to confirm a cancelled order.
     */
    public function cancel(Request $request, string $orderNo)
    {
        $customerId = Auth::guard('customer')->id();

        $order = Order::query()
            ->where('order_no', $orderNo)
            ->where('customer_id', $customerId)
            ->first();

        if (! $order) {
            return back()->with('error', 'Order not found.');
        }

        $reason = trim((string) $request->input('reason', ''));

        try {
            $restored = DB::transaction(function () use ($order, $reason, $customerId) {
                $locked = Order::whereKey($order->id)->lockForUpdate()->first();

                // Re-read under the lock: the status may have moved between
                // the page being rendered and this request arriving.
                if (! $locked || ! $locked->isCancellableByCustomer()) {
                    throw new DomainException('too_late');
                }

                $locked->load('orderItems');
                $restored = $this->reconciler->restoreStockForOrder($locked);

                $locked->status = Order::CANCELLED;
                $locked->save();

                // Refunds are issued by staff from the admin order page, so a
                // paid online order says so on its history where they look.
                $refundDue = $locked->isRefundDueOnCancel();
                $comment = $reason !== ''
                    ? 'Cancelled by customer: ' . mb_substr($reason, 0, 380)
                    : 'Cancelled by customer.';
                if ($refundDue) {
                    $comment .= ' Refund due: ₹' . number_format((float) $locked->payment->amount, 2) . ' paid online.';
                }

                OrderStatusHistory::create([
                    'order_id' => $locked->id,
                    'status'   => Order::CANCELLED,
                    'source'   => OrderStatusHistory::SOURCE_CUSTOMER,
                    'comment'  => $comment,
                ]);

                // A coupon is only ever burned on the same path that commits
                // stock, so the same flag decides whether to give it back.
                if ($restored && ! empty($locked->coupan_code)) {
                    $this->couponService->release($locked->coupan_code);
                }

                return ['restored' => $restored, 'refund_due' => $refundDue];
            });
        } catch (DomainException $e) {
            return back()->with('error', 'This order can no longer be cancelled — it has already been handed to the courier. Please contact support for help.');
        }

        Log::info('Order cancelled by customer.', [
            'order_no'       => $order->order_no,
            'customer_id'    => $customerId,
            'stock_restored' => $restored['restored'],
            'refund_due'     => $restored['refund_due'],
        ]);

        try {
            $customer = Customer::find($customerId);
            if ($customer) {
                $customer->notify(new OrderStatusChanged($order->refresh(), Order::CANCELLED, $reason));
            }
        } catch (\Exception $e) {
            Log::warning('Order cancellation notification failed: ' . $e->getMessage());
        }

        $message = 'Order #' . $order->order_no . ' has been cancelled.';
        if ($restored['refund_due']) {
            $message .= ' Your refund will be processed to your original payment method within 5–7 working days.';
        }

        return back()->with('success', $message);
    }
}
