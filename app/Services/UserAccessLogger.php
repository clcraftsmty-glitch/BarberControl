<?php

namespace App\Services;

use App\Enums\UserAccessEvent;
use App\Models\User;
use App\Models\UserAccessLog;

class UserAccessLogger
{
    public function record(
        UserAccessEvent $event,
        ?User $user = null,
        ?User $actor = null,
        ?string $email = null,
        ?string $details = null,
    ): UserAccessLog {
        return UserAccessLog::query()->create([
            'user_id' => $user?->id,
            'actor_id' => $actor?->id,
            'email' => $email ?? $user?->email,
            'event' => $event,
            'ip_address' => request()?->ip(),
            'user_agent' => mb_substr((string) request()?->userAgent(), 0, 500) ?: null,
            'details' => filled($details) ? mb_substr(trim($details), 0, 500) : null,
            'occurred_at' => now(),
        ]);
    }
}
