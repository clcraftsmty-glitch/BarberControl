<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\WalkInEntry;

class WalkInEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Administrator, UserRole::Receptionist);
    }

    public function start(User $user, WalkInEntry $entry): bool
    {
        return $user->hasRole(UserRole::Administrator, UserRole::Receptionist, UserRole::Barber);
    }

    public function markLeft(User $user, WalkInEntry $entry): bool
    {
        return $user->hasRole(UserRole::Administrator, UserRole::Receptionist);
    }
}
