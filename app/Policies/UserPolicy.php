<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->role === UserRole::Administrator;
    }

    public function view(User $user, User $target): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, User $target): bool
    {
        return $this->viewAny($user);
    }

    public function resetPassword(User $user, User $target): bool
    {
        return $this->viewAny($user);
    }

    public function changeStatus(User $user, User $target): bool
    {
        return $this->viewAny($user) && $user->isNot($target);
    }
}
