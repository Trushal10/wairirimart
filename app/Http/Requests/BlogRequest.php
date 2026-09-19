<?php

namespace App\Http\Requests;

use App\Helper\CommonHelper;
use App\Models\Blog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class BlogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = in_array($this->method(), ['PUT', 'PATCH']);

        $rules = [
            'title'            => ['required', 'string', 'max:255'],
            'category'         => ['nullable', 'string', 'max:100'],
            'author'           => ['nullable', 'string', 'max:100'],
            'excerpt'          => ['nullable', 'string', 'max:500'],
            'content'          => ['required', 'string'],
            'meta_title'       => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'is_active'        => ['nullable', 'boolean'],
            'published_at'     => ['nullable', 'date'],
            'image' => $this->hasFile('image')
                ? [File::types(['jpeg', 'jpg', 'png', 'webp'])->max(5 * 1024)]
                : [$isUpdate ? 'nullable' : 'required'],
        ];

        if ($isUpdate) {
            $blog = $this->route('blog');
            $rules['title'] = ['required', 'string', 'max:255'];
        }

        return $rules;
    }

    public function validated($key = null, $default = null)
    {
        $inputs = parent::validated();
        $inputs['slug'] = $this->makeUniqueSlug($inputs['title']);
        $inputs['is_active'] = (bool) ($inputs['is_active'] ?? false);
        $inputs['content'] = CommonHelper::sanitizeHtml($inputs['content'] ?? '');

        return $inputs;
    }

    protected function makeUniqueSlug(string $title): string
    {
        $base = Str::slug($title, '-') ?: CommonHelper::makeSlug($title);
        $slug = $base;
        $i = 1;

        $ignoreId = optional($this->route('blog'))->id;

        while (Blog::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $slug = $base . '-' . ++$i;
        }

        return $slug;
    }
}
