<?php

namespace App\Http\Requests\Client;

use App\Rules\V3captcha;
use Illuminate\Foundation\Http\FormRequest;

class ContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // The captcha field is only required when we actually have a secret
        // key configured. This lets local dev / staging work without setting
        // up Google reCAPTCHA credentials.
        $captchaConfigured = ! empty(config('services.recaptcha.v3-recaptcha-secret-key'));

        return [
            'name'    => ['required', 'string', 'max:255'],
            'email'   => ['required', 'string', 'email', 'max:255'],
            'phone'   => ['required', 'string', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'city'    => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'recaptcha' => $captchaConfigured
                ? ['required', 'string', new V3captcha]
                : ['nullable'],
            // Honeypot — a hidden field real users never fill. If a bot fills
            // it we reject silently. Cheap layer that works even without keys.
            'website' => ['nullable', 'prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'recaptcha.required' => 'Please complete the captcha challenge and try again.',
            'phone.regex'        => 'Please enter a valid phone number.',
            'website.prohibited' => 'Submission blocked.',
        ];
    }
}
