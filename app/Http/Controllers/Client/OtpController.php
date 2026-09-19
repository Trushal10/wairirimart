<?php

namespace App\Http\Controllers\Client;

use App\Helper\CommonHelper;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\PasswordReset;
use Illuminate\Http\Request;

class OtpController extends Controller
{
    /**
     * Resend the OTP tied to an existing token. Used by the email
     * verify-after-register flow AND the email verify-after-login-with-
     * unverified-account flow — both land on the same #otp-form.
     */
    public function index(Request $request)
    {
        $token = (string) $request->input('token', '');

        if ($token === '') {
            return response()->json(['error' => 'Token not found.'], 404);
        }

        $row = PasswordReset::query()->where('token', $token)->first();
        if (! $row) {
            // Stale/expired token — the client should restart sign-up/sign-in.
            return response()->json(['error' => 'Your session expired. Please sign in again.'], 410);
        }

        // Prefer the customer's actual name (if we have one) so the email
        // greeting isn't a generic "Hi User". `$row->email` may hold a phone
        // for phone-flow rows, so only look up on real email addresses.
        $name = 'there';
        if (filter_var($row->email, FILTER_VALIDATE_EMAIL)) {
            $customer = Customer::query()->where('email', $row->email)->first();
            if ($customer) {
                $name = $customer->name ?: 'there';
            }
        }

        CommonHelper::sendOtpEmail($row->email, $name, $token);

        return response()->json([
            'success' => 'A new OTP has been sent. Please check your inbox.',
        ], 200);
    }
}
