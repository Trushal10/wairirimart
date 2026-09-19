<?php

namespace App\Http\Controllers\Client;

use App\Helper\CommonHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\ResetPasswordRequest;
use App\Models\Customer;
use App\Models\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class ForgotPasswordController extends Controller
{
    public function index()
    {
        return view('client.client-auth.forgotpassword');
    }

    public function forgotPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:50'],
        ]);

        $userExist = Customer::query()->where('email', $validated['email'])->first();
        // Always show the same success response — do not leak whether the
        // account exists (user-enumeration protection).
        $genericMessage = 'If an account exists for that email, an OTP has been sent.';

        if (! empty($userExist)) {
            $token = CommonHelper::sendOtpEmail($validated['email'], $userExist->name);
            return Redirect()->route('client.reset-password-form', ['token' => $token])
                ->with('info', $genericMessage);
        }

        // Log for observability but never reveal to caller.
        Log::info('Forgot-password requested for unknown email', ['email' => $validated['email']]);
        return back()->with('info', $genericMessage);
    }

    public function resetPasswordForm(Request $request)
    {
        return view('client.client-auth.resetPassword', [
            'token' => $request->token,
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        $validated = $request->validated();
        $passwordReset = PasswordReset::query()->where('token', $validated['token'])->first();

        // Token must exist before we can time-check it.
        if (empty($passwordReset)) {
            return back()->with('error', 'Invalid or expired reset link. Please request a new OTP.');
        }

        $createdAt = Carbon::parse($passwordReset->updated_at);
        if ($createdAt->diffInMinutes(Carbon::now(), true) > 5) {
            return back()->with('error', 'The token or OTP has expired. Please request a new OTP.');
        }

        // Constant-time comparison to avoid OTP timing attacks.
        $submittedOtp = (string) ($validated['otp'] ?? '');
        $storedOtp    = (string) $passwordReset->otp;
        if (! hash_equals($storedOtp, $submittedOtp)) {
            return back()->with('error', 'Invalid OTP. Please check the code and try again.');
        }

        $customer = Customer::query()->where('email', $passwordReset->email)->first();
        if (empty($customer)) {
            // Should not happen if token issuance ties to a real user, but be safe.
            PasswordReset::query()->where('email', $passwordReset->email)->delete();
            return back()->with('error', 'Invalid or expired reset link. Please request a new OTP.');
        }

        $customer->password = Hash::make($validated['password']);
        $customer->save();
        PasswordReset::query()->where('email', $passwordReset->email)->delete();

        return redirect()->route('client.login')->with('success', 'Password Reset successfully');
    }
}
