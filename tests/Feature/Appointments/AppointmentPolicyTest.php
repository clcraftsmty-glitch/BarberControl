<?php

namespace Tests\Feature\Appointments;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_and_receptionist_can_manage_appointments(): void
    {
        $appointment = Appointment::factory()->create();

        foreach ([UserRole::Administrator, UserRole::Receptionist] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->assertTrue($user->can('create', Appointment::class));
            $this->assertTrue($user->can('update', $appointment));
        }
    }

    public function test_barber_can_view_but_cannot_manage_appointments(): void
    {
        $user = User::factory()->create(['role' => UserRole::Barber]);
        $appointment = Appointment::factory()->create();

        $this->assertTrue($user->can('viewAny', Appointment::class));
        $this->assertTrue($user->can('view', $appointment));
        $this->assertFalse($user->can('create', Appointment::class));
        $this->assertFalse($user->can('update', $appointment));
    }
}
