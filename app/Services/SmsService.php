<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Driver-agnostic SMS dispatcher.
 *
 * Driver is chosen via `services.sms.driver`. Supported drivers:
 *   - none      (texting switched off on purpose; sends and logs nothing)
 *   - log       (default; writes to laravel.log — safe for local dev)
 *   - msg91     (India — DLT-based)
 *   - fast2sms  (India)
 *   - twilio    (global)
 *
 * Every driver receives an already-normalised E.164-ish phone number, so callers
 * don't need to think about country codes.
 */
class SmsService
{
    /**
     * SMS_DRIVER values that mean "texting is off deliberately".
     *
     * Kept distinct from `log`, which is the accidental-misconfiguration case
     * and warns in production. An empty SMS_DRIVER= counts as off too.
     */
    private const DISABLED = ['none', 'off', 'false', 'null', ''];

    public function send(string $phone, string $message): bool
    {
        $driver = strtolower(trim((string) config('services.sms.driver', 'log')));

        // Checked before anything else so a disabled channel stays completely
        // quiet - no invalid-phone warnings, no driver warning, no work. Runs
        // ahead of normalize() on purpose. Returns true the way Laravel's own
        // null drivers do: the message was accepted and deliberately dropped.
        if (in_array($driver, self::DISABLED, true)) {
            return true;
        }

        $normalized = $this->normalize($phone);
        if (! $normalized) {
            // Log the number we were handed, not the normalised one - that is
            // null right here, which is what made this warning unactionable.
            Log::warning('SMS send skipped: invalid phone', ['phone' => $phone]);
            return false;
        }

        // The log driver is the default, so an unset SMS_DRIVER in production
        // means nothing is ever delivered while every call reports success.
        // Turning SMS off is SMS_DRIVER=none, which never reaches this line.
        if ($driver === 'log' && app()->environment('production')) {
            Log::warning('SMS driver is "log" in production - nothing was actually sent. Set SMS_DRIVER=none to disable texting on purpose, or configure a real driver.');
        }

        try {
            return match ($driver) {
                'msg91'    => $this->sendMsg91($normalized, $message),
                'fast2sms' => $this->sendFast2Sms($normalized, $message),
                'twilio'   => $this->sendTwilio($normalized, $message),
                default    => $this->sendLog($normalized, $message),
            };
        } catch (\Throwable $e) {
            Log::error("SMS send failed [{$driver}]: " . $e->getMessage(), [
                'phone' => $normalized,
            ]);
            return false;
        }
    }

    /* -------------------- drivers -------------------- */

    protected function sendLog(string $phone, string $message): bool
    {
        Log::channel('stack')->info("[SMS-LOG] to={$phone} msg={$message}");
        return true;
    }

    protected function sendMsg91(string $phone, string $message): bool
    {
        $key = config('services.sms.msg91.auth_key');
        if (empty($key)) return $this->missingCreds('msg91');

        $response = Http::withHeaders([
            'authkey'      => $key,
            'accept'       => 'application/json',
            'content-type' => 'application/json',
        ])->post('https://control.msg91.com/api/v5/flow/', array_filter([
            'flow_id'  => config('services.sms.msg91.template_id'),
            'sender'   => config('services.sms.msg91.sender'),
            // normalize() strips the country code; MSG91 wants it back, the
            // same way the Twilio driver below re-adds +91.
            'mobiles'  => '91' . $phone,
            'message'  => $message,
        ], fn ($v) => $v !== null && $v !== ''));

        return $this->succeeded('msg91', $response, fn (array $body) => ($body['type'] ?? null) !== 'error');
    }

    protected function sendFast2Sms(string $phone, string $message): bool
    {
        $key = config('services.sms.fast2sms.api_key');
        if (empty($key)) return $this->missingCreds('fast2sms');

        $response = Http::withHeaders([
            'authorization' => $key,
            'content-type'  => 'application/x-www-form-urlencoded',
        ])->asForm()->post('https://www.fast2sms.com/dev/bulkV2', [
            'route'    => 'q',
            'message'  => $message,
            'language' => 'english',
            'numbers'  => $phone,
        ]);

        return $this->succeeded('fast2sms', $response, fn (array $body) => ($body['return'] ?? true) !== false);
    }

    protected function sendTwilio(string $phone, string $message): bool
    {
        $sid   = config('services.sms.twilio.sid');
        $token = config('services.sms.twilio.token');
        $from  = config('services.sms.twilio.from');
        if (empty($sid) || empty($token) || empty($from)) return $this->missingCreds('twilio');

        $to = str_starts_with($phone, '+') ? $phone : '+91' . $phone;

        $response = Http::withBasicAuth($sid, $token)
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'From' => $from,
                'To'   => $to,
                'Body' => $message,
            ]);

        return $response->successful();
    }

    /* -------------------- helpers -------------------- */

    protected function missingCreds(string $driver): bool
    {
        Log::warning("SMS driver '{$driver}' credentials not configured — falling back to log.");
        return false;
    }

    /**
     * Decide whether a provider actually accepted the message.
     *
     * MSG91 and Fast2SMS both answer 200 OK and report rejections in the body,
     * so checking the HTTP status alone counted every failure as a success and
     * logged nothing. $bodyOk inspects the decoded payload; when the body is
     * not the shape we expect we fall back to the status, so an unrecognised
     * response is never treated as a failure it might not be.
     */
    protected function succeeded(string $driver, Response $response, callable $bodyOk): bool
    {
        if (! $response->successful()) {
            Log::error("SMS send failed [{$driver}]: HTTP {$response->status()}", [
                'body' => $response->body(),
            ]);
            return false;
        }

        $body = $response->json();
        if (is_array($body) && ! $bodyOk($body)) {
            Log::error("SMS rejected by {$driver}", ['body' => $response->body()]);
            return false;
        }

        return true;
    }

    /**
     * Strip non-digits and drop any leading Indian country code.
     * Returns null if the result isn't a plausible mobile number.
     */
    protected function normalize(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';

        // Customers enter the same number as +91 98…, 0091 98…, 098…, 091 98…
        // and plain 98…; only the first and last of those used to survive, so
        // the rest were logged as "invalid phone" and never texted. Indian
        // mobiles start 6-9, so no leading zero is ever significant.
        $digits = ltrim($digits, '0');
        if (str_starts_with($digits, '91') && strlen($digits) === 12) {
            $digits = substr($digits, 2);
        }

        return strlen($digits) === 10 ? $digits : null;
    }
}
