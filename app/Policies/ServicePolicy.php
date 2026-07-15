<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Service;
use App\Models\User;

class ServicePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Service $service): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Administrator);
    }

    public function update(User $user, Service $service): bool
    {
        return $user->hasRole(UserRole::Administrator);
    }

    public function changeStatus(User $user, Service $service): bool
    {
        return $this->update($user, $service);
    }
}
