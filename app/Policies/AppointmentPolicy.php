<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Appointment $appointment): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Administrator, UserRole::Receptionist);
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $user->hasRole(UserRole::Administrator, UserRole::Receptionist);
    }
}
