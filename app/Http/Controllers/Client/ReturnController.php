<?php

namespace App\Http\Controllers\Client;

use App\Helper\CommonHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\ReturnRequest;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\ReturnItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReturnController extends Controller
{
    /**
     * Show the return request form for a specific order.
     */
    public function create(string $orderNo)
    {
        $customerId = Auth::guard('customer')->id();
        $order = Order::query()
            ->where('order_no', $orderNo)
            ->where('customer_id', $customerId)
            ->with(['orderItems.product:id,name,slug', 'orderItems.media', 'latestShipment'])
            ->firstOrFail();

        abort_unless($order->isReturnable(), 403,
            'This order is not eligible for return. It may be outside the return window or not yet delivered.');

        // Compute per-item remaining quantity — a customer can't return more than
        // they bought (minus what's already been returned in prior requests).
        $alreadyReturned = ReturnItem::query()
            ->whereIn('order_item_id', $order->orderItems->pluck('id'))
            ->whereHas('return', fn ($q) => $q->whereNotIn('status', [
                OrderReturn::STATUS_REJECTED,
                OrderReturn::STATUS_CANCELLED,
            ]))
            ->select('order_item_id', DB::raw('SUM(quantity) as returned_qty'))
            ->groupBy('order_item_id')
            ->pluck('returned_qty', 'order_item_id');

        $items = $order->orderItems->map(function ($item) use ($alreadyReturned) {
            $already = (int) ($alreadyReturned[$item->id] ?? 0);
            $item->remaining_qty = max(0, $item->quantity - $already);
            return $item;
        })->filter(fn ($i) => $i->remaining_qty > 0)->values();

        abort_if($items->isEmpty(), 403, 'All items in this order have already been returned.');

        return view('client.returns.create', [
            'order' => $order,
            'items' => $items,
            'reasons' => OrderReturn::REASONS,
        ]);
    }

    /**
     * Store the customer's return request.
     */
    public function store(ReturnRequest $request, string $orderNo)
    {
        $customerId = Auth::guard('customer')->id();
        $order = Order::query()
            ->where('order_no', $orderNo)
            ->where('customer_id', $customerId)
            ->with('orderItems')
            ->firstOrFail();

        abort_unless($order->isReturnable(), 403, 'Return window has closed for this order.');

        $validated = $request->validated();

        // Cross-check every submitted item actually belongs to this order and
        // the requested quantity doesn't exceed what's still eligible.
        $orderItemsById = $order->orderItems->keyBy('id');
        $alreadyReturned = ReturnItem::query()
            ->whereIn('order_item_id', $orderItemsById->keys())
            ->whereHas('return', fn ($q) => $q->whereNotIn('status', [
                OrderReturn::STATUS_REJECTED,
                OrderReturn::STATUS_CANCELLED,
            ]))
            ->select('order_item_id', DB::raw('SUM(quantity) as returned_qty'))
            ->groupBy('order_item_id')
            ->pluck('returned_qty', 'order_item_id');

        foreach ($validated['items'] as $line) {
            $itemId = (int) $line['order_item_id'];
            $qty = (int) $line['quantity'];
            $orderItem = $orderItemsById->get($itemId);
            if (! $orderItem) {
                return back()->with('error', 'One of the selected items is not part of this order.')->withInput();
            }
            $remaining = max(0, $orderItem->quantity - (int) ($alreadyReturned[$itemId] ?? 0));
            if ($qty > $remaining) {
                return back()->with('error', "You can return at most {$remaining} of \"{$orderItem->product?->name}\".")->withInput();
            }
        }

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = CommonHelper::uploadFile($request->file('photo'), 'returns');
        }

        $return = DB::transaction(function () use ($order, $customerId, $validated, $photoPath, $orderItemsById) {
            $return = OrderReturn::create([
                'return_no' => 'RET-TMP-' . uniqid(),
                'order_id' => $order->id,
                'customer_id' => $customerId,
                'status' => OrderReturn::STATUS_REQUESTED,
                'reason' => $validated['reason'],
                'comment' => $validated['comment'] ?? null,
                'photo' => $photoPath,
                'requested_at' => now(),
            ]);
            $return->return_no = OrderReturn::generateReturnNo($return->id);
            $return->save();

            $totalRefund = 0.0;
            foreach ($validated['items'] as $line) {
                $orderItem = $orderItemsById->get((int) $line['order_item_id']);
                ReturnItem::create([
                    'return_id' => $return->id,
                    'order_item_id' => $orderItem->id,
                    'quantity' => (int) $line['quantity'],
                    'unit_price' => (float) $orderItem->price,
                ]);
                $totalRefund += $line['quantity'] * (float) $orderItem->price;
            }
            $return->refund_amount = round($totalRefund, 2);
            $return->save();

            return $return;
        });

        return redirect()
            ->route('client.returns.show', ['returnNo' => $return->return_no])
            ->with('success', 'Return request submitted. Reference: ' . $return->return_no);
    }

    /**
     * Show status of a specific return request.
     */
    public function show(string $returnNo)
    {
        $customerId = Auth::guard('customer')->id();
        $return = OrderReturn::query()
            ->where('return_no', $returnNo)
            ->where('customer_id', $customerId)
            ->with(['items.orderItem.product:id,name,slug', 'items.orderItem.media', 'order:id,order_no,total'])
            ->firstOrFail();

        return view('client.returns.show', [
            'return' => $return,
        ]);
    }

    /**
     * Customer cancels their own pending return request.
     */
    public function cancel(string $returnNo)
    {
        $customerId = Auth::guard('customer')->id();
        $return = OrderReturn::query()
            ->where('return_no', $returnNo)
            ->where('customer_id', $customerId)
            ->firstOrFail();

        if (! in_array($return->status, [OrderReturn::STATUS_REQUESTED], true)) {
            return back()->with('error', 'This return can no longer be cancelled by you. Please contact support.');
        }

        $return->update([
            'status' => OrderReturn::STATUS_CANCELLED,
        ]);
        return back()->with('success', 'Return request cancelled.');
    }
}
