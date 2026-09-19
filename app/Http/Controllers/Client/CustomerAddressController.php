<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\CustomerAddressRequest;
use App\Models\CustomerAddress;
use Illuminate\Support\Facades\Auth;

class CustomerAddressController extends Controller
{
    public function save(CustomerAddressRequest $request)
    {
        $customerId = Auth::guard('customer')->id();
        if (empty($customerId)) {
            return response()->json(['error' => true, 'message' => 'Unauthenticated.'], 401);
        }

        $inputs = $request->validated();
        $inputs['customer_id'] = $customerId;
        $address = CustomerAddress::query()->create($inputs);
        if (! empty($address)) {
            return response()->json(['success' => true, 'message' => 'Address saved successfully'], 200);
        }

        return response()->json(['error' => true, 'message' => 'Failed to save address'], 400);
    }

    public function edit($id)
    {
        $customerId = Auth::guard('customer')->id();
        if (empty($customerId)) {
            return response()->json(['error' => true, 'message' => 'Unauthenticated.'], 401);
        }

        $address = CustomerAddress::query()
            ->where('id', $id)
            ->where('customer_id', $customerId)
            ->first();

        if (empty($address)) {
            return response()->json(['error' => true, 'message' => 'Address not found.'], 404);
        }

        return response()->json(['success' => true, 'address' => $address]);
    }

    public function update($id, CustomerAddressRequest $request)
    {
        $customerId = Auth::guard('customer')->id();
        if (empty($customerId)) {
            return response()->json(['error' => true, 'message' => 'Unauthenticated.'], 401);
        }

        $inputs = $request->validated();
        $address = CustomerAddress::query()
            ->where('id', $id)
            ->where('customer_id', $customerId)
            ->first();

        if (empty($address)) {
            return response()->json(['error' => true, 'message' => 'Address not found.'], 404);
        }

        $address->update($inputs);

        return response()->json(['success' => true, 'message' => 'Address updated successfully'], 200);
    }

    public function delete($id)
    {
        $customerId = Auth::guard('customer')->id();
        if (empty($customerId)) {
            return response()->json(['error' => true, 'message' => 'Unauthenticated.'], 401);
        }

        $deleted = CustomerAddress::query()
            ->where('id', $id)
            ->where('customer_id', $customerId)
            ->delete();

        if (! $deleted) {
            return response()->json(['error' => true, 'message' => 'Address not found.'], 404);
        }

        return response()->json(['success' => true, 'message' => 'Address deleted successfully'], 200);
    }
}
