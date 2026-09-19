<?php

namespace App\Http\Requests;

use App\Helper\CommonHelper;
use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class CategoryRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255', 'unique:categories'],
            'description' => ['required', 'string', 'max:500'],
            // On create: required + must be a file. On update: only validated if a new
            // file is actually uploaded (existing images are sent back as a string).
            // 5 MB ceiling — client-side compression keeps most uploads under 500 KB.
            'image' => $this->hasFile('image')
                ? ['required', File::types(['jpeg', 'jpg', 'png', 'webp'])->max(5 * 1024)]
                : [in_array($this->method(), ['PUT', 'PATCH']) ? 'nullable' : 'required'],
            'status' => ['required'],
            'featured' => ['required'],
        ];

        if (in_array($this->method(), ['PUT', 'PATCH'])) {
            $category = $this->route('category');
            $rules['name'] = [
                'required', 'string', 'max:255',
                Rule::unique(Category::class)->ignore($category),
            ];

            $rules['image'] = array_merge(['nullable'], CommonHelper::getFileValidationRule('image', ['jpeg', 'jpg', 'png', 'webp'], (2 * 1000)));
        }

        return $rules;
    }

    public function validated($key = null, $default = null)
    {
        $inputs = parent::validated();
        $inputs['status'] = $inputs['status'] ?? 0;
        $inputs['featured'] = $inputs['featured'] ?? 0;
        $inputs['slug'] = Str::slug($inputs['name'], '-') ?: CommonHelper::makeSlug($inputs['name']);

        return $inputs;
    }
}
