<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Verify a Google reCAPTCHA v3 token server-side.
 *
 * Behaviour:
 * - If `services.recaptcha.v3-recaptcha-secret-key` is empty (typical local
 *   dev) the rule PASSES silently so the contact form still works without
 *   requiring keys.
 * - When the key is set, the token is sent to Google's siteverify endpoint
 *   and must return `success=true` AND a score above the configured
 *   threshold (default 0.5). Score interpretation: 1.0 = very likely human,
 *   0.0 = very likely a bot.
 */
class V3captcha implements ValidationRule
{
    protected float $threshold;

    public function __construct(?float $threshold = null)
    {
        $this->threshold = $threshold ?? (float) config('services.recaptcha.v3_threshold', 0.5);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $secret = (string) config('services.recaptcha.v3-recaptcha-secret-key', '');

        // Dev-mode / not configured: don't block the form.
        if ($secret === '') {
            return;
        }

        if (! is_string($value) || $value === '') {
            $fail('Please complete the captcha challenge and try again.');
            return;
        }

        try {
            $response = Http::asForm()
                ->timeout(6)
                ->post('https://www.google.com/recaptcha/api/siteverify', [
                    'secret'   => $secret,
                    'response' => $value,
                    'remoteip' => request()->ip(),
                ]);

            if (! $response->ok()) {
                Log::warning('reCAPTCHA verify HTTP error', ['status' => $response->status()]);
                $fail('We could not verify the captcha. Please try again.');
                return;
            }

            $data = $response->json();

            if (empty($data['success'])) {
                Log::info('reCAPTCHA verify rejected', ['errors' => $data['error-codes'] ?? []]);
                $fail('Captcha verification failed. Please refresh and try again.');
                return;
            }

            $score = (float) ($data['score'] ?? 0);
            if ($score < $this->threshold) {
                Log::info('reCAPTCHA score below threshold', [
                    'score'     => $score,
                    'threshold' => $this->threshold,
                    'ip'        => request()->ip(),
                ]);
                $fail('Your submission looks automated. Please try again.');
                return;
            }
        } catch (\Throwable $e) {
            Log::warning('reCAPTCHA verify exception: ' . $e->getMessage());
            // Fail-open on network errors — better to accept a legitimate
            // message than block everyone if Google is unreachable.
        }
    }
}
