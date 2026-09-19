<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReturnController extends Controller
{
    public function index(Request $request)
    {
        $query = OrderReturn::query()
            ->with([
                'order:id,order_no,shipping_name,shipping_email,total',
                'customer:id,name,email,phone',
            ])
            ->withCount('items');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('return_no', 'like', "%{$search}%")
                    ->orWhereHas('order', fn ($oq) => $oq
                        ->where('order_no', 'like', "%{$search}%")
                        ->orWhere('shipping_email', 'like', "%{$search}%")
                        ->orWhere('shipping_name', 'like', "%{$search}%"));
            });
        }

        $returns = $query->latest('id')->paginate(15)->withQueryString();

        // Small header KPIs so the admin knows the queue depth at a glance.
        $counts = OrderReturn::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return Inertia::render('Admin/Return/Index', [
            'returns' => $returns,
            'filters' => $request->only(['search', 'status']),
            'counts' => [
                'requested' => (int) ($counts[OrderReturn::STATUS_REQUESTED] ?? 0),
                'approved' => (int) ($counts[OrderReturn::STATUS_APPROVED] ?? 0),
                'received' => (int) ($counts[OrderReturn::STATUS_RECEIVED] ?? 0),
                'refunded' => (int) ($counts[OrderReturn::STATUS_REFUNDED] ?? 0),
            ],
        ]);
    }

    public function show(OrderReturn $return)
    {
        $return->load([
            'order' => fn ($q) => $q->with('payment', 'latestShipment'),
            'customer:id,name,email,phone',
            'items.orderItem.product:id,name,slug',
            'items.orderItem.media',
        ]);

        return Inertia::render('Admin/Return/Detail', [
            'return' => $return,
            'reasons' => OrderReturn::REASONS,
        ]);
    }

    public function approve(Request $request, OrderReturn $return)
    {
        abort_unless($return->status === OrderReturn::STATUS_REQUESTED, 422, 'Only pending returns can be approved.');

        $return->update([
            'status' => OrderReturn::STATUS_APPROVED,
            'approved_at' => now(),
        ]);
        AuditLogger::record('return.approve', $return, null, 'Return approved.');

        return back()->with('success', 'Return approved. Ask the customer to ship the item(s) back.');
    }

    public function reject(Request $request, OrderReturn $return)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);
        abort_unless($return->status === OrderReturn::STATUS_REQUESTED, 422, 'Only pending returns can be rejected.');

        $return->update([
            'status' => OrderReturn::STATUS_REJECTED,
            'rejected_at' => now(),
            'rejection_reason' => $data['reason'],
        ]);
        AuditLogger::record('return.reject', $return,
            ['reason' => $data['reason']], 'Return rejected.');

        return back()->with('success', 'Return rejected. The customer will see your reason on their return page.');
    }

    public function markReceived(Request $request, OrderReturn $return)
    {
        $data = $request->validate([
            'restock' => ['sometimes', 'boolean'],
        ]);
        abort_unless($return->status === OrderReturn::STATUS_APPROVED, 422,
            'Only approved returns can be marked received.');

        DB::transaction(function () use ($return, $data) {
            $return->update([
                'status' => OrderReturn::STATUS_RECEIVED,
                'received_at' => now(),
                'restock_on_receipt' => (bool) ($data['restock'] ?? $return->restock_on_receipt),
            ]);

            if ($return->restock_on_receipt) {
                foreach ($return->items()->with('orderItem')->get() as $ri) {
                    $qty = (int) $ri->quantity;
                    // Restock the variant first (if the sold line had one)
                    // then the parent product, mirroring the decrement path
                    // in Client\OrderController::save so inventory stays in
                    // sync for variant-based products.
                    if (! empty($ri->orderItem->product_variant_id)) {
                        $variant = \App\Models\ProductVariant::whereKey($ri->orderItem->product_variant_id)
                            ->lockForUpdate()
                            ->first();
                        if ($variant) {
                            $variant->increment('stock', $qty);
                        }
                    }
                    $product = Product::whereKey($ri->orderItem->product_id)
                        ->lockForUpdate()
                        ->first();
                    if ($product) {
                        $product->increment('stock', $qty);
                    }
                }
            }
        });

        AuditLogger::record('return.received', $return,
            ['restocked' => $return->restock_on_receipt], 'Return package received.');

        return back()->with('success',
            'Return marked as received.'
            . ($return->restock_on_receipt ? ' Stock has been restored.' : ''));
    }

    /**
     * Mark a return as refunded. Only allowed after the goods have been
     * physically received. Does NOT itself call the payment gateway —
     * the actual money-movement happens via `Admin\OrderController::refund`
     * which is deep-linked from the return detail page. This action just
     * records that the operator has completed the money-side of the return.
     */
    public function markRefunded(Request $request, OrderReturn $return)
    {
        // Enforce strict state machine: only RECEIVED → REFUNDED. Previously
        // allowed APPROVED → REFUNDED which contradicted the message and
        // allowed refunds without ever receiving the goods.
        abort_unless($return->status === OrderReturn::STATUS_RECEIVED, 422,
            'Only received returns can be marked refunded.');

        $return->update([
            'status' => OrderReturn::STATUS_REFUNDED,
            'refunded_at' => now(),
        ]);
        AuditLogger::record('return.refunded', $return, null, 'Return marked refunded.');

        return back()->with('success', 'Return marked as refunded.');
    }
}
