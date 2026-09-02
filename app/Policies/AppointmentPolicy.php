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

    public function updateStatus(User $user, Appointment $appointment): bool
    {
        return $user->hasRole(UserRole::Administrator);
    }

    public function transition(User $user, Appointment $appointment): bool
    {
        if ($user->hasRole(UserRole::Administrator, UserRole::Receptionist)) {
            return true;
        }

        return $user->role === UserRole::Barber
            && $appointment->barber()->where('user_id', $user->id)->exists();
    }

    public function manageException(User $user, Appointment $appointment): bool
    {
        return $user->hasRole(UserRole::Administrator, UserRole::Receptionist);
    }

    public function registerPayment(User $user, Appointment $appointment): bool
    {
        return $user->hasRole(UserRole::Administrator, UserRole::Receptionist);
    }
}
