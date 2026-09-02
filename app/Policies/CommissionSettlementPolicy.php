<?php

namespace App\Policies;

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\CommissionSettlement;
use App\Models\User;

class CommissionSettlementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Barber
            || $user->hasPermission(UserPermission::ViewFinancialInformation);
    }

    public function view(User $user, CommissionSettlement $settlement): bool
    {
        return $user->hasPermission(UserPermission::ViewFinancialInformation)
            || ($user->role === UserRole::Barber && $settlement->barber()->where('user_id', $user->id)->exists());
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(UserPermission::SettleCommissions);
    }

    public function adjust(User $user): bool
    {
        return $user->hasPermission(UserPermission::SettleCommissions);
    }
}
