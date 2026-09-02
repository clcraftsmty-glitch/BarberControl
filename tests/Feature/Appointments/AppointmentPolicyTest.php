<?php

namespace Tests\Feature\Appointments;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Barber;
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
            $this->assertTrue($user->can('transition', $appointment));
            $this->assertTrue($user->can('registerPayment', $appointment));
            $this->assertSame(
                $role === UserRole::Administrator,
                $user->can('updateStatus', $appointment),
            );
        }
    }

    public function test_barber_can_view_and_transition_only_their_own_appointments(): void
    {
        $user = User::factory()->create(['role' => UserRole::Barber]);
        $barber = Barber::factory()->for($user)->create();
        $appointment = Appointment::factory()->for($barber)->create();
        $otherAppointment = Appointment::factory()->create();

        $this->assertTrue($user->can('viewAny', Appointment::class));
        $this->assertTrue($user->can('view', $appointment));
        $this->assertFalse($user->can('create', Appointment::class));
        $this->assertFalse($user->can('update', $appointment));
        $this->assertFalse($user->can('updateStatus', $appointment));
        $this->assertTrue($user->can('transition', $appointment));
        $this->assertFalse($user->can('transition', $otherAppointment));
        $this->assertFalse($user->can('manageException', $appointment));
        $this->assertFalse($user->can('registerPayment', $appointment));
    }
}
