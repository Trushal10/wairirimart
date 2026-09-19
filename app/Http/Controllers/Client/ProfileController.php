<?php

namespace App\Http\Controllers\Client;

use App\Helper\CommonHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\ProfileRequest;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\ProductMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function index()
    {
        $user = Auth::guard('customer')->user();
        $addresses = CustomerAddress::query()->where('customer_id', Auth::guard('customer')->user()->id)->get();
        $orderHistorys = Order::query()
            ->select('id', 'order_no', 'sub_total', 'shipping', 'discount', 'total', 'status', 'created_at')
            ->where('customer_id', $user['id'])
            ->with([
                'orderItems.product:name,id,slug',
                'orderItems.media' => function ($query) {
                    $query->select('product_id', 'url')->where('type', ProductMedia::IMAGE)->limit(1);
                },
                'payment:id,order_id,type,status',
                'latestShipment',
            ])
            ->withCount('orderItems')
            ->latest()
            ->paginate(6)
            ->withQueryString();

        return view('client.client-auth.profile', [
            'user' => $user,
            'addresses' => $addresses,
            'orderHistorys' => $orderHistorys,
        ]);
    }

    public function updateProfile(ProfileRequest $request)
    {
        $inputs = $request->validated();
        $request->user('customer')->fill($inputs);

        if ($request->user('customer')->isDirty('email')) {
            $request->user('customer')->email_verified_at = null;
        }

        $request->user('customer')->save();

        return redirect()->route('client.profile')->with('success', 'Profile updated successfully');
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password:customer'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);
        $request->user('customer')->update([
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->route('client.profile')->with('success', 'Password updated successfully');
    }

    /** Largest avatar we accept, in kilobytes. Mirrored by the client-side check. */
    private const MAX_AVATAR_KB = 5 * 1024;

    public function updateProfileImage(Request $request)
    {
        $user = Auth::guard('customer')->user();

        // 'required', not 'nullable'. A nullable rule passes when no file
        // arrives at all, so a dropped upload fell through to update([]) and
        // this endpoint answered "Image updated successfully" without having
        // changed anything.
        $request->validate([
            'image' => ['required', File::types(['jpg', 'jpeg', 'png', 'webp'])->max(self::MAX_AVATAR_KB)],
        ]);

        $user->update([
            'image' => CommonHelper::uploadFile($request->file('image'), 'customer', $user->image),
        ]);

        return response()->json(['success' => 'Image updated successfully', 'reload' => true], 200);
    }
}
