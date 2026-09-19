<?php

namespace App\Http\Requests;

use App\Helper\CommonHelper;
use App\Models\ProductMedia;
use Illuminate\Foundation\Http\FormRequest;

class MediaRequest extends FormRequest
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
        $rules = [
            'caption' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'in:'.ProductMedia::IMAGE.','.ProductMedia::VIDEO],
            'action_url' => ['nullable', 'string'],
            'priority' => ['nullable'],
            'image' => ['required_if:type,'.ProductMedia::IMAGE, 'nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:512'],
            'video_url' => ['required_if:type,'.ProductMedia::VIDEO, 'nullable', 'string'],
        ];

        if (in_array($this->method(), ['PUT', 'PATCH'])) {
            // On update, keep the existing image if no new file is uploaded — validate only when a file is present.
            $rules['image'] = array_merge(['nullable'], CommonHelper::getFileValidationRule('image', ['jpeg', 'jpg', 'png', 'webp'], (1 * 500)));
        }

        return $rules;
    }
}
