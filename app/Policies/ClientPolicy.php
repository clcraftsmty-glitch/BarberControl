<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Client $client): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Administrator, UserRole::Receptionist);
    }

    public function update(User $user, Client $client): bool
    {
        return $user->hasRole(UserRole::Administrator, UserRole::Receptionist);
    }

    public function deactivate(User $user, Client $client): bool
    {
        return $this->update($user, $client);
    }
}
