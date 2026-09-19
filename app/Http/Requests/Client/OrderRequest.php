<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class OrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::guard('customer')->check();
    }

    public function rules(): array
    {
        return [
            'payment_method' => ['required', 'string', 'in:cod,razorpay'],
            'address_id' => ['required', 'integer', 'min:1', 'exists:customer_addresses,id'],
            'agree_tos' => ['sometimes', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'payment_method.required' => 'Select any one payment Option.',
            'payment_method.in' => 'Invalid payment method.',
            'address_id.required' => 'Select any one address.',
            'address_id.integer' => 'Address must be a valid numeric value.',
            'address_id.exists' => 'Selected address is invalid.',
            'agree_tos.accepted' => 'Please agree to the terms and conditions before placing the order.',
        ];
    }
}
