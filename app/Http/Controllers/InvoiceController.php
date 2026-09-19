<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;

class InvoiceController extends Controller
{
    /**
     * Customer-facing invoice view.
     * Auth via 'customer' guard + ownership check on order.customer_id.
     */
    public function customerInvoice(string $orderNo)
    {
        $customerId = Auth::guard('customer')->id();
        if (empty($customerId)) {
            return redirect()->route('client.login')->with('info', 'Please sign in to view your invoice.');
        }

        $order = Order::with(['orderItems.product:id,name,sku,hs_code', 'orderItems.variant:id,sku', 'payment', 'customer:id,name,email,phone'])
            ->where('order_no', $orderNo)
            ->where('customer_id', $customerId)
            ->first();

        if (! $order) {
            abort(404, 'Invoice not found.');
        }

        return view('invoices.show', [
            'order'    => $order,
            'settings' => Setting::first(),
        ]);
    }

    /**
     * Admin-facing invoice view. Auth handled by parent middleware.
     */
    public function adminInvoice(Order $order)
    {
        $order->load(['orderItems.product:id,name,sku,hs_code', 'orderItems.variant:id,sku', 'payment', 'customer:id,name,email,phone']);

        return view('invoices.show', [
            'order'    => $order,
            'settings' => Setting::first(),
            'isAdmin'  => true,
        ]);
    }
}
