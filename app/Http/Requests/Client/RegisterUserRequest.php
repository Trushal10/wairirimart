<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class RegisterUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $mode = $this->input('mode', 'email');

        $common = [
            'name' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'mode' => ['required', 'in:email,phone'],
        ];

        if ($mode === 'phone') {
            return array_merge($common, [
                'phone' => ['required', 'digits_between:10,15', 'unique:customers,phone'],
                'email' => ['nullable', 'email', 'max:100', 'unique:customers,email'],
            ]);
        }

        return array_merge($common, [
            'email' => ['required', 'email', 'max:100', 'unique:customers,email'],
            'phone' => ['nullable', 'digits_between:10,15', 'unique:customers,phone'],
        ]);
    }

    public function messages(): array
    {
        return [
            'phone.digits_between' => 'Phone must be between 10 and 15 digits.',
            'email.unique' => 'An account with this email already exists.',
            'phone.unique' => 'An account with this phone number already exists.',
        ];
    }
}
