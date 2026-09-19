<?php

namespace App\Services;

use App\Models\PasswordReset;
use App\Notifications\SendOtpNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class OtpService
{
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_PHONE = 'phone';

    /**
     * Generate + persist an OTP and dispatch it via the requested channel.
     * Returns the reset token (safe to hand back to the frontend).
     */
    public function issue(string $identifier, string $channel, string $displayName = ''): string
    {
        $token = Str::random(50);
        $otp = random_int(100000, 999999);

        PasswordReset::query()->updateOrCreate(
            ['email' => $identifier],
            ['token' => $token, 'otp' => $otp, 'email' => $identifier]
        );

        try {
            $channel === self::CHANNEL_PHONE
                ? $this->sendSms($identifier, $otp, $displayName)
                : $this->sendEmail($identifier, $otp, $displayName, $token);
        } catch (\Throwable $e) {
            Log::error("OtpService dispatch failed [{$channel}]: " . $e->getMessage());
        }

        return $token;
    }

    /**
     * Verify a supplied OTP. Returns the PasswordReset row on success, null otherwise.
     */
    public function verify(string $token, string $otp, int $ttlMinutes = 5): ?PasswordReset
    {
        $row = PasswordReset::query()->where('token', $token)->first();
        if (! $row) return null;

        $createdAt = \Illuminate\Support\Carbon::parse($row->updated_at);
        if ($createdAt->diffInMinutes(now()) > $ttlMinutes) return null;

        if (! hash_equals((string) $row->otp, (string) $otp)) return null;

        return $row;
    }

    public function purge(string $identifier): void
    {
        PasswordReset::query()->where('email', $identifier)->delete();
    }

    /* -------------------- delivery -------------------- */

    protected function sendEmail(string $email, int $otp, string $name, string $token): void
    {
        Notification::route('mail', [$email => $name])
            ->notify(new SendOtpNotification([
                'email' => $email,
                'otp' => $otp,
                'name' => $name,
                'token' => $token,
            ]));
    }

    /**
     * Ship the OTP via the configured SMS driver.
     * Stub logs to the app log unless a driver is configured — swap in Twilio /
     * MSG91 / Fast2SMS by picking a driver via `services.sms.driver`.
     */
    protected function sendSms(string $phone, int $otp, string $name): void
    {
        $driver = config('services.sms.driver', 'log');
        $message = "Hi " . ($name ?: 'there') . ", your " . config('app.name') . " OTP is {$otp}. Valid for 5 minutes.";

        switch ($driver) {
            case 'msg91':
                $this->msg91($phone, $otp, $message);
                return;
            case 'twilio':
                $this->twilio($phone, $message);
                return;
            case 'fast2sms':
                $this->fast2sms($phone, $otp, $message);
                return;
            case 'log':
            default:
                Log::info("[SMS-OTP] to {$phone}: {$message}");
        }
    }

    protected function msg91(string $phone, int $otp, string $message): void
    {
        $key = config('services.sms.msg91.auth_key');
        $template = config('services.sms.msg91.template_id');
        if (empty($key) || empty($template)) {
            Log::warning('MSG91 credentials missing, falling back to log driver.');
            Log::info("[SMS-OTP fallback] {$phone}: {$message}");
            return;
        }
        \Illuminate\Support\Facades\Http::withHeaders(['authkey' => $key])
            ->acceptJson()
            ->post('https://control.msg91.com/api/v5/otp', [
                'template_id' => $template,
                'mobile' => preg_replace('/\D/', '', $phone),
                'otp' => $otp,
            ]);
    }

    protected function twilio(string $phone, string $message): void
    {
        $sid = config('services.sms.twilio.sid');
        $token = config('services.sms.twilio.token');
        $from = config('services.sms.twilio.from');
        if (empty($sid) || empty($token) || empty($from)) {
            Log::warning('Twilio credentials missing, falling back to log driver.');
            Log::info("[SMS-OTP fallback] {$phone}: {$message}");
            return;
        }
        \Illuminate\Support\Facades\Http::withBasicAuth($sid, $token)
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'To' => $phone,
                'From' => $from,
                'Body' => $message,
            ]);
    }

    protected function fast2sms(string $phone, int $otp, string $message): void
    {
        $key = config('services.sms.fast2sms.api_key');
        if (empty($key)) {
            Log::warning('Fast2SMS credentials missing, falling back to log driver.');
            Log::info("[SMS-OTP fallback] {$phone}: {$message}");
            return;
        }
        \Illuminate\Support\Facades\Http::withHeaders(['authorization' => $key])
            ->post('https://www.fast2sms.com/dev/bulkV2', [
                'route' => 'otp',
                'variables_values' => (string) $otp,
                'numbers' => preg_replace('/\D/', '', $phone),
            ]);
    }
}
