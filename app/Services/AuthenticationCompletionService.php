<?php

namespace App\Services;

use App\Enums\UserAccessEvent;
use App\Models\User;

class AuthenticationCompletionService
{
    public function __construct(private UserAccessLogger $accessLogger) {}

    public function complete(User $user, ?UserAccessEvent $event = UserAccessEvent::Login): void
    {
        session()->put('two_factor_verified_for', $user->id);
        session()->forget(['two_factor_pending_user_id', 'two_factor_setup_secret']);
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
            'last_two_factor_at' => $user->hasConfirmedTwoFactor() ? now() : $user->last_two_factor_at,
        ])->saveQuietly();
        $this->accessLogger->record($event ?? UserAccessEvent::Login, $user);
    }
}
