<?php

namespace App\Policies;

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\CashRegisterSession;
use App\Models\User;

class CashRegisterSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(UserPermission::ViewFinancialInformation);
    }

    public function view(User $user, CashRegisterSession $session): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, CashRegisterSession $session): bool
    {
        return $user->is_active && $user->hasRole(UserRole::Administrator, UserRole::Receptionist);
    }

    public function adjust(User $user, CashRegisterSession $session): bool
    {
        return $user->hasPermission(UserPermission::AdjustCash);
    }
}
