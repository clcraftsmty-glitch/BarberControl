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

class AppointmentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_receptionist_can_create_appointment_and_end_is_calculated_from_service(): void
    {
        [$actor, $client, $barber, $service] = $this->resources();
        $this->actingAs($actor);

        Livewire::test(Calendar::class)
            ->call('openCreate', '2026-07-20T10:00:00-06:00')
            ->set('form.client_id', $client->id)
            ->set('form.barber_id', $barber->id)
            ->set('form.service_id', $service->id)
            ->set('form.price', '350.00')
            ->set('form.status', AppointmentStatus::Confirmed->value)
            ->set('form.notes', 'Llegar diez minutos antes')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('appointments-changed');

        $appointment = Appointment::query()->firstOrFail();
        $this->assertSame('2026-07-20 10:00', $appointment->starts_at->format('Y-m-d H:i'));
        $this->assertSame('2026-07-20 10:45', $appointment->ends_at->format('Y-m-d H:i'));
        $this->assertSame('350.00', $appointment->price);
        $this->assertSame($actor->id, $appointment->created_by);
        $this->assertSame($actor->id, $appointment->updated_by);
    }

    public function test_overlapping_appointments_for_same_barber_are_rejected(): void
    {
        [$actor, $client, $barber, $service] = $this->resources();
        $scheduler = app(AppointmentScheduler::class);
        $scheduler->create($this->data($client, $barber, $service, '2026-07-20 10:00'), $actor);

        try {
            $scheduler->create($this->data($client, $barber, $service, '2026-07-20 10:30'), $actor);
            $this->fail('La cita superpuesta debió ser rechazada.');
        } catch (ValidationException $exception) {
            $this->assertSame('El barbero ya tiene una cita en ese horario.', $exception->errors()['form.starts_at'][0]);
        }

        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_adjacent_appointments_and_cancelled_slots_are_allowed(): void
    {
        [$actor, $client, $barber, $service] = $this->resources();
        $scheduler = app(AppointmentScheduler::class);

        $scheduler->create($this->data($client, $barber, $service, '2026-07-20 09:00', AppointmentStatus::Cancelled), $actor);
        $scheduler->create($this->data($client, $barber, $service, '2026-07-20 09:00'), $actor);
        $scheduler->create($this->data($client, $barber, $service, '2026-07-20 09:45'), $actor);

        $this->assertDatabaseCount('appointments', 3);
    }

    public function test_appointment_outside_barber_schedule_is_rejected(): void
    {
        [$actor, $client, $barber, $service] = $this->resources();

        $this->expectException(ValidationException::class);
        app(AppointmentScheduler::class)->create(
            $this->data($client, $barber, $service, '2026-07-20 08:30'),
            $actor,
        );
    }

    public function test_barber_must_be_assigned_to_selected_service(): void
    {
        [$actor, $client, $barber] = $this->resources();
        $unassignedService = Service::factory()->create(['duration_minutes' => 30]);

        try {
            app(AppointmentScheduler::class)->create(
                $this->data($client, $barber, $unassignedService, '2026-07-20 10:00'),
                $actor,
            );
            $this->fail('El servicio no asignado debió ser rechazado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('form.service_id', $exception->errors());
        }
    }

    public function test_drag_move_recalculates_end_and_tracks_modifier(): void
    {
        [$creator, $client, $barber, $service] = $this->resources();
        $modifier = User::factory()->create(['role' => UserRole::Administrator]);
        $scheduler = app(AppointmentScheduler::class);
        $appointment = $scheduler->create($this->data($client, $barber, $service, '2026-07-20 10:00'), $creator);

        $moved = $scheduler->move($appointment, '2026-07-20 12:00', $modifier);

        $this->assertSame('2026-07-20 12:00', $moved->starts_at->format('Y-m-d H:i'));
        $this->assertSame('2026-07-20 12:45', $moved->ends_at->format('Y-m-d H:i'));
        $this->assertSame($creator->id, $moved->created_by);
        $this->assertSame($modifier->id, $moved->updated_by);
    }

    public function test_feed_returns_calendar_events_with_different_barber_colors(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Barber]);
        $client = Client::factory()->create();
        $service = Service::factory()->create();
        $firstBarber = Barber::factory()->create();
        $secondBarber = Barber::factory()->create();

        Appointment::factory()->create([
            'client_id' => $client,
            'barber_id' => $firstBarber,
            'service_id' => $service,
            'starts_at' => '2026-07-20 10:00',
            'ends_at' => '2026-07-20 10:30',
        ]);
        Appointment::factory()->create([
            'client_id' => $client,
            'barber_id' => $secondBarber,
            'service_id' => $service,
            'starts_at' => '2026-07-20 11:00',
            'ends_at' => '2026-07-20 11:30',
        ]);

        $response = $this->actingAs($viewer)->getJson(route('appointments.feed', [
            'start' => '2026-07-20T00:00:00-06:00',
            'end' => '2026-07-21T00:00:00-06:00',
        ]));

        $response->assertOk()->assertJsonCount(2);
        $this->assertNotSame(
            $response->json('0.backgroundColor'),
            $response->json('1.backgroundColor'),
        );
        $this->assertFalse($response->json('0.editable'));
    }

    /** @return array{User, Client, Barber, Service} */
    private function resources(): array
    {
        $actor = User::factory()->create(['role' => UserRole::Receptionist]);
        $client = Client::factory()->create();
        $barber = Barber::factory()->create();
        $service = Service::factory()->create([
            'duration_minutes' => 45,
            'price' => 350,
        ]);
        $barber->services()->attach($service);

        return [$actor, $client, $barber, $service];
    }

    private function data(
        Client $client,
        Barber $barber,
        Service $service,
        string $startsAt,
        AppointmentStatus $status = AppointmentStatus::Pending,
    ): array {
        return [
            'client_id' => $client->id,
            'barber_id' => $barber->id,
            'service_id' => $service->id,
            'starts_at' => $startsAt,
            'price' => $service->price,
            'status' => $status->value,
            'notes' => null,
        ];
    }
}
