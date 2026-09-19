<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SliderRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255', Rule::unique('sliders', 'title')->ignore($this->slider)],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'boolean'],
        ];

        return $rules;
    }

    public function validated($key = null, $default = null)
    {
        $inputs = parent::validated();
        $inputs['status'] ??= 0;

        return $inputs;
    }
}
