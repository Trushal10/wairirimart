<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class HomeVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $creating = $this->isMethod('POST');

        return [
            'title' => ['nullable', 'string', 'max:80'],
            'subtitle' => ['nullable', 'string', 'max:160'],
            // Short, muted clips. MP4 (H.264) and WebM play in every current
            // browser; .mov does not, so it is not offered. 50 MB is well above
            // what a 15–30s web clip needs, and below the server's 2 GB limit.
            'video' => [$creating ? 'required' : 'nullable', File::types(['mp4', 'webm'])->max(50 * 1024)],
            // Shown before the clip loads and while it is not the one playing.
            'poster' => ['nullable', File::types(['jpeg', 'jpg', 'png', 'webp'])->max(1024)],
            'remove_poster' => ['nullable', 'boolean'],
            // Site-relative path or an http(s) URL only.
            'link_url' => ['nullable', 'string', 'max:255', 'regex:#^(/(?!/)|https?://)#i'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'status' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'link_url.regex' => 'Use a path starting with / or a full http(s):// URL.',
        ];
    }
}
