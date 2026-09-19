<?php

namespace App\Http\Requests\Client;

use App\Helper\CommonHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::guard('customer')->check();
    }

    /**
     * When the customer isn't signed in we want the client-side JS to show a
     * clear "please sign in" prompt — not a generic "Something went wrong".
     * Returning a JSON body with a proper message + explicit 401 status
     * makes that possible.
     */
    protected function failedAuthorization()
    {
        abort(response()->json([
            'success' => false,
            'message' => 'Please sign in to submit a review.',
        ], 401));
    }

    public function rules(): array
    {
        return [
            'product_id' => 'required|exists:products,id',
            'rate' => 'required|integer|min:1|max:5',
            'title' => 'required|string|max:255',
            'review' => 'required|string|max:2000',
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:100',
            // Match the "Max size: 2MB" hint shown in the review form.
            'image' => array_merge(['nullable'], CommonHelper::getFileValidationRule('image', ['jpeg', 'jpg', 'png', 'webp'], (2 * 1024))),
        ];
    }
}
