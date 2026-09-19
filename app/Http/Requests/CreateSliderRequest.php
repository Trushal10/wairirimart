<?php

namespace App\Http\Requests;

use App\Helper\CommonHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class CreateSliderRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'image' => ['required', File::types(['jpeg', 'jpg', 'png', 'webp'])->max(1 * 1024)],
            'summary' => ['nullable', 'string', 'max:300'],
            'status' => ['required'],
        ];

        if (in_array($this->method(), ['PUT', 'PATCH'])) {
            $rules['image'] = array_merge(['nullable'], CommonHelper::getFileValidationRule('image', ['jpeg', 'jpg', 'png', 'webp'], (2 * 1000)));
        }

        return $rules;
    }

    public function validated($key = null, $default = null)
    {
        $inputs = parent::validated();
        $inputs['status'] = $inputs['status'] ?? 0;

        return $inputs;
    }
}
