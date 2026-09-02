<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;

class AuditLogger
{
    private const HIDDEN = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
        'access_token', 'app_secret', 'verify_token', 'updated_at', 'created_at',
        'last_login_at', 'last_login_ip', 'last_two_factor_at',
    ];

    public function record(
        string $action,
        string $description,
        ?Model $model = null,
        ?array $before = null,
        ?array $after = null,
    ): ?AuditLog {
        if (! Schema::hasTable('audit_logs')) {
            return null;
        }

        return AuditLog::query()->create([
            'actor_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => $model ? $model::class : null,
            'auditable_id' => $model ? (string) $model->getKey() : null,
            'description' => mb_substr($description, 0, 500),
            'before_values' => $this->sanitize($before),
            'after_values' => $this->sanitize($after),
            'ip_address' => request()?->ip(),
            'user_agent' => mb_substr((string) request()?->userAgent(), 0, 500) ?: null,
            'occurred_at' => now(),
        ]);
    }

    private function sanitize(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        return Arr::except($values, self::HIDDEN);
    }
}
