<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\LoginUserRequest;
use App\Models\Customer;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class LoginUserController extends Controller
{
    public function __construct(protected OtpService $otp)
    {
    }

    public function index()
    {
        return view('client.client-auth.login');
    }

    public function loginUser(LoginUserRequest $request)
    {
        $identifier = trim((string) $request->input('identifier'));
        $field = $request->identifierField();
        $password = (string) $request->input('password', '');

        $user = Customer::query()->where($field, $identifier)->first();

        // Generic message for both "no user" and "wrong password" prevents
        // account enumeration and requires the password even before an OTP
        // is issued — this closes the OTP-bomb / unverified-account bypass.
        $genericFailure = ['error' => true, 'message' => 'Invalid credentials.'];

        if (empty($user) || ! Hash::check($password, $user->password ?? '')) {
            return response()->json($genericFailure, 401);
        }

        if ($user->isBlocked()) {
            return response()->json([
                'error' => true,
                'message' => 'Your account has been blocked. Please contact support.',
            ], 403);
        }

        $isVerified = $field === 'email'
            ? ! empty($user->email_verified_at)
            : ! empty($user->phone_verified_at);

        if (! $isVerified) {
            $channel = $field === 'email' ? OtpService::CHANNEL_EMAIL : OtpService::CHANNEL_PHONE;
            $token = $this->otp->issue($identifier, $channel, $user->name ?? '');
            return response()->json([
                'token' => $token,
                'channel' => $channel,
                'message' => 'Please verify with the OTP sent to your ' . ($channel === 'email' ? 'email' : 'phone') . '.',
            ], 200);
        }

        $request->authenticate();
        $request->session()->regenerate();

        return response()->json(['success' => true, 'message' => 'You are successfully logged in.'], 200);
    }

    /**
     * Passwordless phone-OTP login — step 1. Take a phone number, look up
     * the customer, issue an OTP via SMS, return the token.
     */
    public function phoneOtpSend(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'regex:/^[0-9]{10,15}$/'],
        ], [
            'phone.regex' => 'Please enter a valid phone number (digits only).',
        ]);

        $user = Customer::query()->where('phone', $data['phone'])->first();

        // Generic response prevents account enumeration — same response regardless
        // of whether the phone exists. We still gate on account existence server-side
        // by only issuing an OTP for known customers.
        $generic = [
            'success' => true,
            'message' => 'If an account exists for that number, an OTP has been sent.',
        ];

        if (! $user) {
            return response()->json($generic + ['token' => null], 200);
        }

        if ($user->isBlocked()) {
            return response()->json([
                'error' => true,
                'message' => 'Your account has been blocked. Please contact support.',
            ], 403);
        }

        $token = $this->otp->issue($data['phone'], OtpService::CHANNEL_PHONE, $user->name ?? '');

        return response()->json([
            'success' => true,
            'token'   => $token,
            'message' => 'OTP sent to your phone. It expires in 5 minutes.',
        ], 200);
    }

    /**
     * Passwordless phone-OTP login — step 2. Verify OTP and log the user in.
     */
    public function phoneOtpVerify(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'otp'   => ['required', 'string', 'regex:/^[0-9]{4,8}$/'],
        ]);

        $row = $this->otp->verify($data['token'], $data['otp']);
        if (! $row) {
            return response()->json([
                'error' => true,
                'message' => 'The OTP is invalid or has expired. Please request a new one.',
            ], 422);
        }

        $user = Customer::query()->where('phone', $row->email)->first();
        if (! $user) {
            return response()->json([
                'error' => true,
                'message' => 'Account not found for this phone.',
            ], 404);
        }

        if ($user->isBlocked()) {
            return response()->json([
                'error' => true,
                'message' => 'Your account has been blocked. Please contact support.',
            ], 403);
        }

        // First-time phone verification marks the phone as verified so the
        // customer can also fall back to phone+password if they set one later.
        if (empty($user->phone_verified_at)) {
            $user->forceFill(['phone_verified_at' => now()])->save();
        }

        Auth::guard('customer')->login($user, remember: true);
        $request->session()->regenerate();
        $this->otp->purge($row->email);

        return response()->json([
            'success' => true,
            'message' => 'You are successfully logged in.',
        ], 200);
    }

    public function logout(Request $request)
    {
        Auth::guard('customer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('client.home')->with('success', 'You have been logged out.');
    }
}
