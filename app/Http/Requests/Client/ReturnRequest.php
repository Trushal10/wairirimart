<?php

namespace App\Http\Requests\Client;

use App\Helper\CommonHelper;
use App\Models\OrderReturn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::guard('customer')->check();
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'in:' . implode(',', array_keys(OrderReturn::REASONS))],
            'comment' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer', 'exists:order_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'photo' => array_merge(
                ['nullable'],
                CommonHelper::getImageValidationRule('photo')
            ),
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Pick at least one item to return.',
            'items.*.quantity.min' => 'Quantity must be at least 1.',
            'reason.in' => 'Please choose a valid reason.',
        ];
    }
}
