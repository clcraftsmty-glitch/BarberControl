<?php

namespace Tests\Feature\Appointments;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\User;
use App\Services\AppointmentWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AppointmentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_appointment_follows_the_operational_status_sequence_until_pending_payment(): void
    {
        $actor = User::factory()->create(['role' => UserRole::Receptionist]);
        $appointment = Appointment::factory()->create(['status' => AppointmentStatus::Pending]);
        $workflow = app(AppointmentWorkflow::class);

        foreach ([
            AppointmentStatus::Confirmed,
            AppointmentStatus::Arrived,
            AppointmentStatus::InService,
            AppointmentStatus::PendingPayment,
        ] as $target) {
            $appointment = $workflow->transition($appointment, $target, $actor);
            $this->assertSame($target, $appointment->status);
            $this->assertSame($actor->id, $appointment->updated_by);
        }
    }

    public function test_invalid_status_jump_is_rejected(): void
    {
        $actor = User::factory()->create(['role' => UserRole::Receptionist]);
        $appointment = Appointment::factory()->create(['status' => AppointmentStatus::Pending]);

        $this->expectException(ValidationException::class);

        app(AppointmentWorkflow::class)->transition(
            $appointment,
            AppointmentStatus::InService,
            $actor,
        );
    }

    public function test_barber_cannot_start_a_second_service_when_previous_service_overran_its_schedule(): void
    {
        Carbon::setTestNow('2026-07-17 15:08:00');
        $actor = User::factory()->create(['role' => UserRole::Administrator]);
        $barber = Barber::factory()->create(['display_name' => 'BARBER 2']);
        Appointment::factory()->for($barber)->create([
            'starts_at' => now()->subMinutes(46),
            'ends_at' => now()->subMinutes(16),
            'status' => AppointmentStatus::InService,
            'service_started_at' => now()->subMinutes(45),
        ]);
        $candidate = Appointment::factory()->for($barber)->create([
            'starts_at' => now(),
            'ends_at' => now()->addMinutes(30),
            'status' => AppointmentStatus::Arrived,
            'arrived_at' => now()->subMinutes(5),
        ]);

        try {
            app(AppointmentWorkflow::class)->transition(
                $candidate,
                AppointmentStatus::InService,
                $actor,
                true,
            );
            $this->fail('No se debe iniciar un segundo servicio con el mismo barbero.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'BARBER 2 ya tiene un servicio en curso. Finalízalo antes de iniciar otro.',
                $exception->errors()['status'][0],
            );
        }

        $this->assertSame(AppointmentStatus::Arrived, $candidate->refresh()->status);
        Carbon::setTestNow();
    }

    public function test_operational_transitions_record_waiting_and_service_times(): void
    {
        $actor = User::factory()->create(['role' => UserRole::Receptionist]);
        $appointment = Appointment::factory()->create(['status' => AppointmentStatus::Confirmed]);
        $workflow = app(AppointmentWorkflow::class);

        Carbon::setTestNow('2026-07-16 09:00:00');
        $appointment = $workflow->transition($appointment, AppointmentStatus::Arrived, $actor);

        $this->assertTrue($appointment->arrived_at->equalTo(now()));
        $this->assertNull($appointment->service_started_at);

        Carbon::setTestNow('2026-07-16 09:08:15');
        $appointment = $workflow->transition($appointment, AppointmentStatus::InService, $actor);

        $this->assertSame(495, $appointment->waitingDurationSeconds());
        $this->assertTrue($appointment->service_started_at->equalTo(now()));

        Carbon::setTestNow('2026-07-16 09:38:45');
        $appointment = $workflow->transition($appointment, AppointmentStatus::PendingPayment, $actor);

        $this->assertSame(1830, $appointment->serviceDurationSeconds());
        $this->assertSame(2325, $appointment->totalDurationSeconds());
        $this->assertTrue($appointment->service_finished_at->equalTo(now()));

        Carbon::setTestNow();
    }

    public function test_appointment_cannot_be_completed_without_a_registered_sale(): void
    {
        $actor = User::factory()->create(['role' => UserRole::Administrator]);
        $appointment = Appointment::factory()->create(['status' => AppointmentStatus::PendingPayment]);

        try {
            app(AppointmentWorkflow::class)->transition(
                $appointment,
                AppointmentStatus::Completed,
                $actor,
            );
            $this->fail('La cita no debe terminar sin pago.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame(AppointmentStatus::PendingPayment, $appointment->refresh()->status);
    }

    public function test_receptionist_can_apply_exception_before_completion(): void
    {
        $actor = User::factory()->create(['role' => UserRole::Receptionist]);
        $appointment = Appointment::factory()->create(['status' => AppointmentStatus::InService]);

        $appointment = app(AppointmentWorkflow::class)->transition(
            $appointment,
            AppointmentStatus::Rescheduled,
            $actor,
        );

        $this->assertSame(AppointmentStatus::Rescheduled, $appointment->status);
    }
}
