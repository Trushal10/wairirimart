<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CouponController extends Controller
{
    public function index(Request $request)
    {
        $query = Coupon::with('customer:id,name,email')
            ->orderBy('created_at', 'desc');

        if ($search = $request->input('search')) {
            $query->where('code', 'like', "%{$search}%");
        }

        if ($request->input('status') === 'active') {
            $query->where('is_active', true);
        } elseif ($request->input('status') === 'inactive') {
            $query->where('is_active', false);
        }

        return Inertia::render('Admin/Coupon/Index', [
            'coupons' => $query->paginate(20)->withQueryString(),
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Coupon/Create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code'               => 'required|string|max:50|unique:coupons,code',
            'type'               => 'required|in:fixed,percent',
            'value'              => 'required|numeric|min:0.01',
            'min_order_amount'   => 'nullable|numeric|min:0',
            'max_discount_amount'=> 'nullable|numeric|min:0',
            'usage_limit'        => 'nullable|integer|min:1',
            'starts_at'          => 'nullable|date',
            'expires_at'         => 'nullable|date|after_or_equal:starts_at',
            'is_active'          => 'boolean',
        ]);

        $data['code'] = strtoupper($data['code']);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['min_order_amount'] = $data['min_order_amount'] ?? 0;

        Coupon::create($data);

        return redirect()->route('admin.coupons.index')->with('success', 'Coupon created.');
    }

    public function edit(Coupon $coupon)
    {
        return Inertia::render('Admin/Coupon/Create', [
            'coupon' => $coupon->load('customer:id,name,email'),
        ]);
    }

    public function update(Request $request, Coupon $coupon)
    {
        $data = $request->validate([
            'code'               => 'required|string|max:50|unique:coupons,code,' . $coupon->id,
            'type'               => 'required|in:fixed,percent',
            'value'              => 'required|numeric|min:0.01',
            'min_order_amount'   => 'nullable|numeric|min:0',
            'max_discount_amount'=> 'nullable|numeric|min:0',
            'usage_limit'        => 'nullable|integer|min:1',
            'starts_at'          => 'nullable|date',
            'expires_at'         => 'nullable|date|after_or_equal:starts_at',
            'is_active'          => 'boolean',
        ]);

        $data['code'] = strtoupper($data['code']);
        $data['is_active'] = (bool) ($data['is_active'] ?? $coupon->is_active);
        $data['min_order_amount'] = $data['min_order_amount'] ?? 0;

        $coupon->update($data);

        return redirect()->route('admin.coupons.index')->with('success', 'Coupon updated.');
    }

    public function delete(Coupon $coupon)
    {
        $coupon->delete();
        return back()->with('success', 'Coupon deleted.');
    }

    public function toggle(Coupon $coupon)
    {
        $coupon->update(['is_active' => ! $coupon->is_active]);
        return back()->with('success', $coupon->is_active ? 'Coupon activated.' : 'Coupon deactivated.');
    }
}
