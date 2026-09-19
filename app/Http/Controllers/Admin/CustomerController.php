<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::query()
            ->withCount('orders')
            ->withSum(['orders as spent_total' => function ($q) {
                $q->whereIn('status', ['confirmed', 'delivered']);
            }], 'total')
            ->orderByDesc('created_at');

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $status = $request->input('status');
        if ($status === 'active') {
            $query->whereNull('blocked_at');
        } elseif ($status === 'blocked') {
            $query->whereNotNull('blocked_at');
        }

        $verified = $request->input('verified');
        if ($verified === 'email') {
            $query->whereNotNull('email_verified_at');
        } elseif ($verified === 'phone') {
            $query->whereNotNull('phone_verified_at');
        } elseif ($verified === 'unverified') {
            $query->whereNull('email_verified_at')->whereNull('phone_verified_at');
        }

        $customers = $query->paginate(20)->withQueryString();

        // Header stats — small counts so we can afford separate queries.
        $stats = [
            'total'   => Customer::query()->count(),
            'active'  => Customer::query()->whereNull('blocked_at')->count(),
            'blocked' => Customer::query()->whereNotNull('blocked_at')->count(),
            'new_7d'  => Customer::query()->where('created_at', '>=', now()->subDays(7))->count(),
        ];

        return Inertia::render('Admin/Customer/Index', [
            'customers' => $customers,
            'filters'   => $request->only(['search', 'status', 'verified']),
            'stats'     => $stats,
        ]);
    }

    public function show(Customer $customer)
    {
        $customer->loadCount('orders')
            ->load('addresses');

        // Aggregates: total spent (confirmed/delivered only), open orders, last order.
        $spentTotal = (float) $customer->orders()
            ->whereIn('status', ['confirmed', 'delivered'])
            ->sum('total');

        $openOrders = $customer->orders()
            ->whereIn('status', ['pending', 'confirmed'])
            ->count();

        $lastOrder = $customer->orders()->orderByDesc('created_at')->first();

        // Recent orders (last 10) with per-order item summary.
        $recentOrders = $customer->orders()
            ->withCount('orderItems')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get([
                'id', 'order_no', 'sub_total', 'shipping', 'discount', 'total',
                'status', 'created_at', 'customer_id',
            ]);

        return Inertia::render('Admin/Customer/Show', [
            'customer'     => $customer,
            'stats'        => [
                'orders_count' => (int) $customer->orders_count,
                'spent_total'  => $spentTotal,
                'open_orders'  => (int) $openOrders,
                'last_order_at' => optional($lastOrder)->created_at,
            ],
            'recent_orders' => $recentOrders,
        ]);
    }

    public function block(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $customer->forceFill([
            'blocked_at'   => now(),
            'block_reason' => $data['reason'] ?? null,
        ])->save();

        return back()->with('success', 'Customer blocked. Their active sessions will be terminated.');
    }

    public function unblock(Customer $customer)
    {
        $customer->forceFill([
            'blocked_at'   => null,
            'block_reason' => null,
        ])->save();

        return back()->with('success', 'Customer unblocked.');
    }

    public function delete(Customer $customer)
    {
        try {
            $customer->delete();
            return back()->with('success', 'Customer deleted.');
        } catch (\Throwable $e) {
            $message = 'Something went wrong.';
            if ($e->getCode() == 23000) {
                $message = 'This customer has orders and cannot be deleted. Consider blocking instead.';
            }
            return back()->with('error', $message);
        }
    }
}
