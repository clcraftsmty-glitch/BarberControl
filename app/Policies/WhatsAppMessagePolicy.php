<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\WhatsAppMessage;

class WhatsAppMessagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Administrator, UserRole::Receptionist);
    }

    public function view(User $user, WhatsAppMessage $message): bool
    {
        return $this->viewAny($user);
    }

    public function retry(User $user, WhatsAppMessage $message): bool
    {
        return $this->viewAny($user);
    }
}
