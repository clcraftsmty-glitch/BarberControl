<?php

namespace App\Policies;

use App\Enums\SaleStatus;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\Sale;
use App\Models\User;

class SalePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(UserPermission::ViewFinancialInformation);
    }

    public function view(User $user, Sale $sale): bool
    {
        return $this->viewAny($user)
            || ($user->hasRole(UserRole::Administrator, UserRole::Receptionist) && $sale->created_by === $user->id);
    }

    public function print(User $user, Sale $sale): bool
    {
        return $user->is_active && $user->hasRole(UserRole::Administrator, UserRole::Receptionist);
    }

    public function cancel(User $user, Sale $sale): bool
    {
        return $user->hasPermission(UserPermission::CancelSales)
            && $sale->status === SaleStatus::Completed;
    }

    public function refund(User $user, Sale $sale): bool
    {
        return $this->cancel($user, $sale);
    }
}
