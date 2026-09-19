<?php

namespace App\Http\Requests\Client;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = Auth::guard('customer')->user();
        $rules = [
            'name' => ['required', 'string', 'max:25'],
            'email' => ['string', 'email', 'max:50', Rule::unique(Customer::class)->ignore($user)],
            'phone' => ['required', 'numeric', 'max_digits:20', Rule::unique(Customer::class)->ignore($user)],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:50'],
        ];

        return $rules;
    }
}
