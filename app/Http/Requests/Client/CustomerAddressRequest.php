<?php

namespace App\Http\Requests\Client;

use App\Models\CustomerAddress;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CustomerAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::guard('customer')->check();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:100'],
            'phone' => ['required', 'digits_between:10,15'],
            'city' => ['required', 'string', 'max:50'],
            'pincode' => ['required', 'digits_between:4,10'],
            'state' => ['required', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:' . implode(',', CustomerAddress::TYPES)],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.digits_between' => 'Phone number must be between 10 and 15 digits.',
            'pincode.digits_between' => 'Pincode must be between 4 and 10 digits.',
        ];
    }
}
