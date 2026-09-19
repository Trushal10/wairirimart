<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryPartner;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\Setting;
use App\Notifications\OrderStatusChanged;
use App\Services\Payment\PaymentReconciler;
use App\Services\ShipmentService;
use App\Support\Barcode128;
use App\Services\ShiprocketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Razorpay\Api\Api;

class OrderController extends Controller
{
    private const TRANSITIONS = [
        Order::PENDING => [Order::CONFIRMED, Order::CANCELLED],
        Order::CONFIRMED => [Order::COMPLETED, Order::CANCELLED],
        Order::COMPLETED => [],
        Order::CANCELLED => [],
    ];

    public function index(Request $request)
    {
        $query = Order::query()
            ->withCount('orderItems')
            ->with(['latestShipment' => function ($q) {
                // Use fully-qualified columns — `latestOfMany` adds an inner join
                // that also references `order_id`, so bare column names collide.
                $q->select(Shipment::summaryColumns());
            }]);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('order_no', 'like', "%{$search}%")
                    ->orWhere('shipping_name', 'like', "%{$search}%")
                    ->orWhere('shipping_email', 'like', "%{$search}%")
                    ->orWhere('shipping_phone', 'like', "%{$search}%");
            });
        }

        $orders = $query->latest('id')->paginate(10)->withQueryString();

        // Status KPI counts — independent of the current status filter so the
        // strip always reflects the whole store, not the visible slice.
        $counts = Order::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return Inertia::render('Admin/Order/Index', [
            'orders' => $orders,
            'filters' => $request->only(['search', 'status']),
            'counts' => [
                'pending'   => (int) ($counts[Order::PENDING] ?? 0),
                'confirmed' => (int) ($counts[Order::CONFIRMED] ?? 0),
                'delivered' => (int) ($counts[Order::COMPLETED] ?? 0),
                'canceled'  => (int) ($counts[Order::CANCELLED] ?? 0),
            ],
        ]);
    }

    public function detail(Order $order)
    {
        $order->load([
            'orderItems.product:id,name,sku',
            'orderItems.media' => function ($query) {
                $query->select('product_id', 'url')->limit(1);
            },
            'payment',
            'shipments' => fn ($q) => $q->latest('id'),
            'latestShipment',
            'statusHistory',
            'customer:id,name,email,phone',
        ]);

        $partners = DeliveryPartner::where('is_active', true)->get(['id', 'name', 'code']);

        return Inertia::render('Admin/Order/Detail', [
            'order' => $order,
            'partners' => $partners,
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:' . implode(',', [
                Order::PENDING, Order::CONFIRMED, Order::COMPLETED, Order::CANCELLED,
            ]),
            'comment' => 'nullable|string|max:500',
        ]);

        $order = Order::findOrFail($id);
        $newStatus = $request->status;
        $allowed = self::TRANSITIONS[$order->status] ?? [];

        if ($order->status !== $newStatus && ! in_array($newStatus, $allowed, true)) {
            return back()->with('error', "Cannot change status from {$order->status} to {$newStatus}.");
        }
        if ($order->status === $newStatus) {
            return back()->with('info', 'Status unchanged.');
        }

        DB::transaction(function () use ($order, $newStatus, $request) {
            // Cancelling has to put the stock back. This used to just flip the
            // status, so every order an admin cancelled kept its inventory
            // reserved forever and the catalogue slowly under-reported what was
            // actually on the shelf. restoreStockForOrder() is gated on the
            // order's stock_committed_at stamp, so an order whose stock was
            // never taken (a prepaid order abandoned before payment) is left
            // alone rather than being credited stock it never had.
            if ($newStatus === Order::CANCELLED) {
                $order->load('orderItems');
                app(PaymentReconciler::class)->restoreStockForOrder($order);
            }

            $order->status = $newStatus;
            $order->save();
            OrderStatusHistory::create([
                'order_id'    => $order->id,
                'shipment_id' => $order->latestShipment?->id,
                'status'      => $newStatus,
                'source'      => OrderStatusHistory::SOURCE_ADMIN,
                'comment'     => $request->input('comment') ?: 'Status changed by admin.',
            ]);
        });

        // Notify customer of status change
        try {
            $customer = $order->customer;
            if ($customer) {
                $customer->notify(new OrderStatusChanged(
                    $order,
                    $newStatus,
                    $request->input('comment') ?? '',
                ));
            }
        } catch (\Exception $e) {
            Log::warning('OrderStatusChanged notification failed: ' . $e->getMessage());
        }

        return back()->with('success', 'Order status updated successfully.');
    }

    /**
     * Assign delivery partner + create shipment on provider.
     */
    public function addDelivery(Request $request, Order $order, ShipmentService $shipmentService)
    {
        $validated = $request->validate([
            'delivery_partner_id' => 'required|exists:delivery_partners,id',
        ]);
        $partner = DeliveryPartner::findOrFail($validated['delivery_partner_id']);

        try {
            $shipmentService->createForOrder($order->load('orderItems.product', 'payment'), $partner);
        } catch (\Throwable $e) {
            Log::error('addDelivery exception: ' . $e->getMessage(), ['order_id' => $order->id]);
            return back()->with('error', 'Delivery partner set but shipment creation failed. Check logs.');
        }

        return back()->with('success', 'Delivery partner assigned.');
    }

    /**
     * Assign an AWB via Shiprocket (optionally a specific courier_id).
     */
    public function assignAwb(Request $request, Shipment $shipment, ShipmentService $shipmentService)
    {
        $request->validate(['courier_id' => 'nullable|string|max:50']);
        try {
            $shipment = $shipmentService->assignAwb($shipment, $request->input('courier_id'));
        } catch (\Throwable $e) {
            Log::error('assignAwb exception: ' . $e->getMessage(), ['shipment_id' => $shipment->id]);
            return back()->with('error', 'AWB assignment failed. Check logs.');
        }
        // Service marks the remark on failure paths (missing shipment_id, Shiprocket
        // reject, etc.) but does not throw. Surface that remark so the operator sees
        // the actual cause (e.g. "Wrong pickup location") instead of a fake success.
        $shipment->refresh();
        if (empty($shipment->awb_code)) {
            return back()->with('error', $shipment->remark ?: 'AWB not assigned — see shipment remark.');
        }
        return back()->with('success', 'AWB assigned: ' . $shipment->awb_code);
    }

    public function requestPickup(Shipment $shipment, ShipmentService $shipmentService)
    {
        try {
            $shipmentService->requestPickup($shipment);
        } catch (\Throwable $e) {
            Log::error('requestPickup exception: ' . $e->getMessage(), ['shipment_id' => $shipment->id]);
            return back()->with('error', 'Pickup request failed.');
        }
        return back()->with('success', 'Pickup requested.');
    }

    public function generateLabel(Shipment $shipment, ShipmentService $shipmentService)
    {
        try {
            $shipmentService->generateLabel($shipment);
        } catch (\Throwable $e) {
            Log::error('generateLabel exception: ' . $e->getMessage(), ['shipment_id' => $shipment->id]);
            return back()->with('error', 'Label generation failed.');
        }
        return back()->with('success', 'Label generated.');
    }

    public function syncTracking(Shipment $shipment, ShipmentService $shipmentService)
    {
        try {
            $shipmentService->syncTracking($shipment);
        } catch (\Throwable $e) {
            Log::error('syncTracking exception: ' . $e->getMessage(), ['shipment_id' => $shipment->id]);
            return back()->with('error', 'Tracking sync failed.');
        }
        return back()->with('success', 'Tracking synced.');
    }

    public function cancelShipment(Request $request, Shipment $shipment, ShipmentService $shipmentService)
    {
        $request->validate(['reason' => 'nullable|string|max:500']);
        try {
            $shipmentService->cancel($shipment, $request->input('reason', 'Cancelled from admin.'));
        } catch (\Throwable $e) {
            Log::error('cancelShipment exception: ' . $e->getMessage(), ['shipment_id' => $shipment->id]);
            return back()->with('error', 'Cancel failed.');
        }
        return back()->with('success', 'Shipment cancelled.');
    }

    /**
     * Render a printable shipping label.
     *
     * Preference order:
     *   ?prefer=inhouse → always render the themed in-house label (Code-128 barcode)
     *   Otherwise      → redirect to the courier's PDF (Shadowfax / Shiprocket / etc.)
     *                    when we have one — that's the label the pickup driver expects
     *   Fallback       → in-house label so the operator is never blocked
     */
    public function label(Order $order, Request $request)
    {
        $order->load(['orderItems.product:id,name', 'payment', 'latestShipment']);
        $shipment = $order->latestShipment;
        $prefer = $request->query('prefer'); // 'inhouse' | 'courier' | null

        if ($prefer !== 'inhouse' && $shipment && ! empty($shipment->label_url)) {
            return redirect()->away($shipment->label_url);
        }

        $barcodeText = $shipment?->awb_code ?: $order->order_no;
        $barcodeSvg = Barcode128::svg($barcodeText, 44, 2);

        $settings = Setting::query()->first();
        $returnAddress = $settings->address ?? null;
        $paymentType = optional($order->payment)->type;
        $shipmentWeight = $shipment?->weight ?? 0.5;

        return view('admin.labels.shipping', [
            'order' => $order,
            'shipment' => $shipment,
            'barcodeSvg' => $barcodeSvg,
            'barcodeText' => $barcodeText,
            'returnAddress' => $returnAddress,
            'paymentType' => $paymentType,
            'shipmentWeight' => $shipmentWeight,
            'courierLabelUrl' => $shipment?->label_url,
        ]);
    }

    /**
     * Live serviceability check for a given pincode / weight.
     * Returns JSON so the admin panel can populate a courier picker.
     */
    public function checkServiceability(Order $order, ShiprocketService $shiprocket, Request $request)
    {
        $pickup = config('services.shiprocket.pickup_pincode');
        if (empty($pickup)) {
            return response()->json(['error' => true, 'message' => 'Pickup pincode not configured.'], 500);
        }
        $result = $shiprocket->checkServiceability(
            (string) $pickup,
            (string) $order->shipping_pincode,
            (float) ($request->input('weight', 0.5)),
            (int) ($request->input('cod_amount', optional($order->payment)->type === 'cod' ? (int) $order->total : 0))
        );
        return response()->json($result);
    }

    /**
     * Trigger a Razorpay refund (partial or full).
     */
    public function refund(Request $request, Order $order)
    {
        $validated = $request->validate([
            'amount' => 'nullable|numeric|min:0.01',
            'reason' => 'nullable|string|max:500',
        ]);

        $payment = $order->payment;
        if (! $payment || ! $payment->isRefundable()) {
            return back()->with('error', 'This order has no refundable payment.');
        }

        $remaining = (float) $payment->amount - (float) $payment->refunded_amount;
        $amount = isset($validated['amount']) ? (float) $validated['amount'] : $remaining;
        if ($amount <= 0 || $amount > $remaining) {
            return back()->with('error', 'Refund amount is invalid.');
        }

        try {
            $api = new Api(config('services.razorpay.key'), config('services.razorpay.secret'));
            $refund = $api->payment
                ->fetch($payment->payment_id)
                ->refund([
                    'amount' => (int) round($amount * 100),
                    'speed' => 'normal',
                    'notes' => [
                        'reason' => $validated['reason'] ?? 'Admin initiated refund',
                        'order_no' => $order->order_no,
                    ],
                ]);

            $refundArray = $refund->toArray();
            $newRefundedTotal = min((float) $payment->amount, (float) $payment->refunded_amount + $amount);
            $isFull = $newRefundedTotal >= (float) $payment->amount;

            DB::transaction(function () use ($payment, $order, $newRefundedTotal, $isFull, $refundArray, $amount, $validated) {
                $payment->update([
                    'refund_id' => $refundArray['id'] ?? null,
                    'refunded_amount' => $newRefundedTotal,
                    'refunded_at' => now(),
                    'status' => $isFull ? Payment::STATUS_REFUNDED : Payment::STATUS_PARTIALLY_REFUNDED,
                    'meta' => array_merge((array) $payment->meta, ['refund_manual' => $refundArray]),
                ]);
                if ($isFull && $order->status !== Order::CANCELLED) {
                    $order->status = Order::CANCELLED;
                    $order->save();
                    // put stock back for full refunds
                    foreach ($order->orderItems as $item) {
                        $product = Product::lockForUpdate()->find($item->product_id);
                        if ($product) {
                            $product->increment('stock', $item->quantity);
                        }
                    }
                }
                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'status' => $order->status,
                    'source' => OrderStatusHistory::SOURCE_ADMIN,
                    'comment' => 'Refund initiated ₹' . number_format($amount, 2)
                        . ($validated['reason'] ?? '' ? ' — ' . $validated['reason'] : ''),
                    'meta' => $refundArray,
                ]);
            });

            return back()->with('success', 'Refund initiated for ₹' . number_format($amount, 2));
        } catch (\Throwable $e) {
            Log::error('Razorpay refund failed: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'payment_id' => $payment->payment_id,
            ]);
            return back()->with('error', 'Refund failed: ' . $e->getMessage());
        }
    }
}
