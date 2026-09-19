<?php

namespace App\Http\Requests\Client;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string', 'min:8'],
        ];
    }

    public function messages(): array
    {
        return [
            'identifier.required' => 'Please enter your email or phone number.',
        ];
    }

    public function identifierField(): string
    {
        $id = trim((string) $this->input('identifier'));
        return filter_var($id, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $credentials = [
            $this->identifierField() => trim((string) $this->input('identifier')),
            'password' => $this->input('password'),
        ];

        if (! Auth::guard('customer')->attempt($credentials)) {
            RateLimiter::hit($this->throttleKey());
            throw ValidationException::withMessages([
                'identifier' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) return;
        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());
        throw ValidationException::withMessages([
            'identifier' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::lower((string) $this->input('identifier')) . '|' . $this->ip();
    }
}
