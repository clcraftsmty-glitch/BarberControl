<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\BusinessSetting;
use App\Models\User;

class BusinessSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->role === UserRole::Administrator;
    }

    public function update(User $user, BusinessSetting $settings): bool
    {
        return $this->viewAny($user);
    }
}
