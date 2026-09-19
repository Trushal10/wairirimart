<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\RegisterUserRequest;
use App\Http\Requests\Client\VerifyOtpRequest;
use App\Models\Customer;
use App\Services\OtpService;
use Illuminate\Support\Facades\Hash;

class RegisterUserController extends Controller
{
    public function __construct(protected OtpService $otp)
    {
    }

    public function save(RegisterUserRequest $request)
    {
        $inputs = $request->validated();
        $mode = $inputs['mode'] ?? 'email';

        $inputs['password'] = Hash::make($inputs['password']);
        unset($inputs['password_confirmation'], $inputs['mode']);

        $customer = Customer::query()->create($inputs);
        if (empty($customer)) {
            return response()->json(['error' => 'Registration failed. Please try again.'], 500);
        }

        if ($mode === 'phone' && ! empty($customer->phone)) {
            $token = $this->otp->issue($customer->phone, OtpService::CHANNEL_PHONE, $customer->name);
            return response()->json([
                'success' => true,
                'channel' => 'phone',
                'token' => $token,
                'identifier' => $this->maskPhone($customer->phone),
                'message' => 'OTP sent to your phone number. Please verify.',
            ], 200);
        }

        $token = $this->otp->issue($customer->email, OtpService::CHANNEL_EMAIL, $customer->name);
        return response()->json([
            'success' => true,
            'channel' => 'email',
            'token' => $token,
            'identifier' => $this->maskEmail($customer->email),
            'message' => 'OTP sent to your email address. Please verify.',
        ], 200);
    }

    public function verifyOtp(VerifyOtpRequest $request)
    {
        $inputs = $request->validated();
        $reset = $this->otp->verify($inputs['token'], $inputs['otp']);

        if (! $reset) {
            return response()->json(['error' => true, 'message' => 'Invalid or expired OTP.'], 200);
        }

        $identifier = $reset->email;
        $customer = Customer::query()
            ->where('email', $identifier)
            ->orWhere('phone', $identifier)
            ->first();

        if (! $customer) {
            return response()->json(['error' => true, 'message' => 'Account not found.'], 404);
        }

        // We reuse the same reset row for either channel — email or phone.
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $customer->email_verified_at = now();
        } else {
            $customer->phone_verified_at = now();
        }
        $customer->save();

        $this->otp->purge($identifier);

        return response()->json(['success' => true], 200);
    }

    /* --------------------------- helpers --------------------------- */

    protected function maskEmail(string $email): string
    {
        if (! str_contains($email, '@')) return $email;
        [$user, $domain] = explode('@', $email, 2);
        $keep = min(2, max(1, strlen($user) - 2));
        return substr($user, 0, $keep) . str_repeat('•', max(2, strlen($user) - $keep)) . '@' . $domain;
    }

    protected function maskPhone(string $phone): string
    {
        $len = strlen($phone);
        if ($len <= 4) return $phone;
        return str_repeat('•', $len - 4) . substr($phone, -4);
    }
}
