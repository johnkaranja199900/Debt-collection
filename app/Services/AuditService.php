<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Central audit trail. Secrets are stripped before persisting (master spec 47).
 */
class AuditService
{
    private const SECRET_KEYS = ['password', 'api_key', 'secret', 'token', 'passkey', 'sender_id'];

    public function log(string $action, ?Model $model = null, array $old = [], array $new = [], array $extra = []): void
    {
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'model_type' => $model?->getMorphClass(),
            'model_id' => $model?->getKey(),
            'old_values' => $this->sanitize($old) ?: ($extra ? $this->sanitize($extra) : null),
            'new_values' => $this->sanitize($new) ?: null,
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 500),
        ]);
    }

    private function sanitize(array $data): array
    {
        array_walk_recursive($data, function (&$value, $key) {
            foreach (self::SECRET_KEYS as $secret) {
                if (is_string($key) && str_contains(strtolower($key), $secret)) {
                    $value = '[REDACTED]';
                }
            }
        });

        return $data;
    }
}
