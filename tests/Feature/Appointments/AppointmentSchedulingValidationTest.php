<?php

namespace Tests\Feature\Appointments;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Livewire\Appointments\Calendar;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use App\Services\AppointmentScheduler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AppointmentSchedulingValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_barber_overlap_is_rejected_and_only_administrator_can_confirm_override(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $receptionist = User::factory()->create(['role' => UserRole::Receptionist]);
        $barber = Barber::factory()->create();
        $service = Service::factory()->create(['duration_minutes' => 60]);
        $barber->services()->attach($service);
        $scheduler = app(AppointmentScheduler::class);

        $scheduler->create($this->data(Client::factory()->create(), $barber, $service, '2026-07-20 10:00'), $receptionist);

        try {
            $scheduler->create($this->data(Client::factory()->create(), $barber, $service, '2026-07-20 10:30'), $receptionist);
            $this->fail('La superposición debió requerir una excepción.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('schedule.overlap', $exception->errors());
        }

        try {
            $scheduler->create($this->data(Client::factory()->create(), $barber, $service, '2026-07-20 10:30'), $receptionist, true);
            $this->fail('Recepción no debe autorizar excepciones.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('schedule.override', $exception->errors());
        }

        $scheduler->create($this->data(Client::factory()->create(), $barber, $service, '2026-07-20 10:30'), $administrator, true);
        $this->assertDatabaseCount('appointments', 2);
    }

    public function test_existing_client_appointment_on_same_day_returns_warning(): void
    {
        $actor = User::factory()->create(['role' => UserRole::Receptionist]);
        $client = Client::factory()->create();
        $service = Service::factory()->create(['duration_minutes' => 30]);
        $firstBarber = Barber::factory()->create();
        $secondBarber = Barber::factory()->create();
        $firstBarber->services()->attach($service);
        $secondBarber->services()->attach($service);
        $scheduler = app(AppointmentScheduler::class);

        $scheduler->create($this->data($client, $firstBarber, $service, '2026-07-20 10:00'), $actor);

        try {
            $scheduler->create($this->data($client, $secondBarber, $service, '2026-07-20 15:00'), $actor);
            $this->fail('La segunda cita del cliente debió mostrar una advertencia.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'El cliente ya tiene otra cita programada este día.',
                $exception->errors()['schedule.client_day'][0],
            );
        }
    }

    public function test_administrator_confirms_schedule_exception_from_calendar_form(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $barber = Barber::factory()->create();
        $service = Service::factory()->create(['duration_minutes' => 60, 'price' => 300]);
        $barber->services()->attach($service);
        $existing = Appointment::factory()->create([
            'barber_id' => $barber,
            'service_id' => $service,
            'starts_at' => '2026-07-20 10:00',
            'ends_at' => '2026-07-20 11:00',
        ]);
        $newClient = Client::factory()->create();

        $this->actingAs($administrator);

        Livewire::test(Calendar::class)
            ->call('openCreate', '2026-07-20T10:30:00-06:00')
            ->set('form.client_id', $newClient->id)
            ->set('form.barber_id', $barber->id)
            ->set('form.service_id', $service->id)
            ->set('form.price', 300)
            ->call('save')
            ->assertSet('showScheduleOverrideModal', true)
            ->assertSee('El barbero ya tiene una cita en ese horario.')
            ->call('confirmScheduleOverride')
            ->assertHasNoErrors()
            ->assertSet('showScheduleOverrideModal', false);

        $this->assertDatabaseCount('appointments', 2);
        $this->assertDatabaseHas('appointments', [
            'client_id' => $newClient->id,
            'barber_id' => $barber->id,
        ]);
        $this->assertNotNull($existing);
    }

    private function data(Client $client, Barber $barber, Service $service, string $startsAt): array
    {
        return [
            'client_id' => $client->id,
            'barber_id' => $barber->id,
            'service_id' => $service->id,
            'starts_at' => $startsAt,
            'price' => $service->price,
            'status' => AppointmentStatus::Pending->value,
            'notes' => null,
        ];
    }
}
