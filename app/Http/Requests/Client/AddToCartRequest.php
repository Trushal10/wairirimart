<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class AddToCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'productId' => ['required', 'integer', 'min:1', 'exists:products,id'],
            // Optional — present when the product has real variants; must belong
            // to the same product (enforced in the controller after look-up).
            'variantId' => ['nullable', 'integer', 'min:1', 'exists:product_variants,id'],
            'quantity'  => ['required', 'integer', 'min:1', 'max:999'],
            'size'      => ['nullable', 'string', 'max:200'],
            'color'     => ['nullable', 'string', 'max:200'],
        ];
    }
}
