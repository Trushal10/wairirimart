<?php

namespace App\Services;

use App\Models\AdminActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuditLogger
{
    public static function record(string $action, ?Model $subject = null, ?array $changes = null, ?string $comment = null): void
    {
        try {
            AdminActivityLog::create([
                'user_id' => Auth::id(),
                'action' => $action,
                'subject_type' => $subject ? get_class($subject) : null,
                'subject_id' => $subject?->getKey(),
                'ip' => request()?->ip(),
                'user_agent' => mb_substr((string) request()?->userAgent(), 0, 500),
                'changes' => $changes ? self::redactSecrets($changes) : null,
                'comment' => $comment,
            ]);
        } catch (\Throwable $e) {
            Log::warning('AuditLogger failed: ' . $e->getMessage());
        }
    }

    /**
     * Redact obviously-secret values before persisting the diff.
     */
    protected static function redactSecrets(array $data): array
    {
        $needle = ['secret', 'password', 'token', 'api_key', 'key_secret', 'client_secret', 'licence_key', 'private'];
        $walk = function (&$item, $key) use (&$walk, $needle) {
            if (is_array($item)) {
                array_walk($item, $walk);
                return;
            }
            $lc = strtolower((string) $key);
            foreach ($needle as $needleWord) {
                if (str_contains($lc, $needleWord) && is_string($item) && $item !== null) {
                    $item = '••••••';
                    return;
                }
            }
        };
        array_walk($data, $walk);
        return $data;
    }
}
