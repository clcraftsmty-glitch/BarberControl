<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Barber;
use App\Models\User;

class BarberPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Barber $barber): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Administrator);
    }

    public function update(User $user, Barber $barber): bool
    {
        return $user->hasRole(UserRole::Administrator);
    }

    public function changeStatus(User $user, Barber $barber): bool
    {
        return $this->update($user, $barber);
    }
}
