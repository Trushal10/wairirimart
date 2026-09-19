<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    private const ALLOWED_PROVIDERS = ['google'];

    public function redirect(string $provider)
    {
        abort_unless(in_array($provider, self::ALLOWED_PROVIDERS, true), 404);

        $config = config("services.{$provider}");
        if (empty($config['client_id']) || empty($config['client_secret'])) {
            return redirect()->route('client.login')
                ->with('error', ucfirst($provider) . ' sign-in is not configured yet.');
        }

        return Socialite::driver($provider)->redirect();
    }

    public function callback(Request $request, string $provider)
    {
        abort_unless(in_array($provider, self::ALLOWED_PROVIDERS, true), 404);

        try {
            // Use stateful mode — a browser web-flow needs Laravel's session-backed
            // state check to guard against CSRF. `->stateless()` is only for API/mobile.
            $social = Socialite::driver($provider)->user();
        } catch (\Throwable $e) {
            Log::warning("Socialite {$provider} callback failed: " . $e->getMessage());

            $friendly = 'Sign-in with ' . ucfirst($provider) . ' failed. Please try again.';
            // Give the operator a hint if the failure is credential-shaped.
            if (str_contains($e->getMessage(), 'invalid_client')
                || str_contains($e->getMessage(), '401 Unauthorized')
            ) {
                $friendly = 'Google rejected our credentials. Check GOOGLE_CLIENT_SECRET in .env.';
            } elseif (str_contains($e->getMessage(), 'redirect_uri_mismatch')) {
                $friendly = 'Google redirect URI mismatch. Make sure the URI in Google Console matches GOOGLE_REDIRECT_URI.';
            }

            return redirect()->route('client.login')->with('error', $friendly);
        }

        $email = $social->getEmail();
        $googleId = $social->getId();
        if (empty($email) && empty($googleId)) {
            return redirect()->route('client.login')
                ->with('error', 'Could not retrieve your account details from ' . ucfirst($provider) . '.');
        }

        $customer = DB::transaction(function () use ($provider, $googleId, $email, $social) {
            $existing = Customer::query()
                ->when($provider === 'google' && $googleId, fn ($q) => $q->orWhere('google_id', $googleId))
                ->when($email, fn ($q) => $q->orWhere('email', $email))
                ->first();

            if ($existing) {
                $existing->fill([
                    'google_id' => $provider === 'google' ? ($googleId ?: $existing->google_id) : $existing->google_id,
                    'provider' => $existing->provider ?: $provider,
                    'avatar' => $social->getAvatar() ?: $existing->avatar,
                    'name' => $existing->name ?: ($social->getName() ?: 'Customer'),
                ]);
                if (empty($existing->email_verified_at) && $email) {
                    $existing->email_verified_at = now();
                }
                $existing->save();
                return $existing;
            }

            return Customer::create([
                'name' => $social->getName() ?: 'Customer',
                'email' => $email ?: ($googleId . '@' . $provider . '.local'),
                'google_id' => $provider === 'google' ? $googleId : null,
                'provider' => $provider,
                'avatar' => $social->getAvatar(),
                'password' => bcrypt(Str::random(40)),
                'email_verified_at' => $email ? now() : null,
            ]);
        });

        Auth::guard('customer')->login($customer, true);
        $request->session()->regenerate();

        return redirect()
            ->intended(route('client.home'))
            ->with('success', 'Signed in with ' . ucfirst($provider) . '.');
    }
}
