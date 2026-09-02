<?php

namespace Tests\Feature\Appointments;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Livewire\Appointments\Calendar;
use App\Livewire\Appointments\Today;
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
            $this->assertSame('El barbero ya tiene una cita en ese horario.', $exception->errors()['schedule.overlap'][0]);
        }

        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_adjacent_appointments_and_cancelled_slots_are_allowed(): void
    {
        [$actor, $client, $barber, $service] = $this->resources();
        $scheduler = app(AppointmentScheduler::class);

        Appointment::factory()->create([
            'client_id' => $client,
            'barber_id' => $barber,
            'service_id' => $service,
            'starts_at' => '2026-07-20 09:00',
            'ends_at' => '2026-07-20 09:45',
            'status' => AppointmentStatus::Cancelled,
        ]);
        $scheduler->create($this->data(Client::factory()->create(), $barber, $service, '2026-07-20 09:00'), $actor);
        $scheduler->create($this->data(Client::factory()->create(), $barber, $service, '2026-07-20 09:45'), $actor);

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
        $viewer = User::factory()->create(['role' => UserRole::Receptionist]);
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
        $this->assertTrue($response->json('0.editable'));
    }

    public function test_monthly_calendar_displays_scheduled_appointments_and_status_flow(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $client = Client::factory()->create(['first_name' => 'Marco', 'last_name' => 'Mensual']);
        $service = Service::factory()->create(['name' => 'Corte mensual']);
        $appointment = Appointment::factory()->create([
            'client_id' => $client,
            'service_id' => $service,
            'starts_at' => today()->setTime(10, 0),
            'ends_at' => today()->setTime(10, 30),
            'status' => AppointmentStatus::Confirmed,
        ]);
        $this->actingAs($administrator);

        Livewire::test(Calendar::class)
            ->assertSet('month', today()->format('Y-m'))
            ->assertSee('Marco Mensual')
            ->assertSee('Corte mensual')
            ->assertSee('Confirmada')
            ->call('nextMonth')
            ->assertSet('month', today()->addMonth()->format('Y-m'))
            ->call('previousMonth')
            ->assertSet('month', today()->format('Y-m'));

        $this->assertNotNull($appointment);
    }

    public function test_barber_sees_only_their_appointments_in_daily_agenda(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Barber]);
        $barber = Barber::factory()->for($viewer)->create(['display_name' => 'Rodrigo']);
        $otherBarber = Barber::factory()->create();
        $service = Service::factory()->create(['name' => 'Corte clásico']);
        $ownClient = Client::factory()->create(['first_name' => 'Cliente', 'last_name' => 'Propio']);
        $otherClient = Client::factory()->create(['first_name' => 'Cliente', 'last_name' => 'Ajeno']);

        Appointment::factory()->create([
            'client_id' => $ownClient,
            'barber_id' => $barber,
            'service_id' => $service,
            'starts_at' => today()->setTime(10, 0),
            'ends_at' => today()->setTime(10, 30),
        ]);
        Appointment::factory()->create([
            'client_id' => $otherClient,
            'barber_id' => $otherBarber,
            'service_id' => $service,
            'starts_at' => today()->setTime(11, 0),
            'ends_at' => today()->setTime(11, 30),
        ]);

        $this->actingAs($viewer);

        Livewire::test(Today::class)
            ->assertSee('Orden del día')
            ->assertSee('Cliente Propio')
            ->assertSee('Corte clásico')
            ->assertDontSee('Cliente Ajeno');
    }

    public function test_daily_agenda_hides_empty_sections_and_filters_from_summary_cards(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $waitingClient = Client::factory()->create(['first_name' => 'Cliente', 'last_name' => 'En Espera']);
        $finishedClient = Client::factory()->create(['first_name' => 'Cliente', 'last_name' => 'Finalizado']);

        Appointment::factory()->create([
            'client_id' => $waitingClient,
            'starts_at' => today()->setTime(10, 0),
            'ends_at' => today()->setTime(10, 30),
            'status' => AppointmentStatus::Arrived,
        ]);
        Appointment::factory()->create([
            'client_id' => $finishedClient,
            'starts_at' => today()->setTime(9, 0),
            'ends_at' => today()->setTime(9, 30),
            'status' => AppointmentStatus::Completed,
        ]);
        $this->actingAs($administrator);

        Livewire::test(Today::class)
            ->assertSeeInOrder(['Próximas', 'En espera', 'En servicio', 'Por cobrar', 'Finalizadas'])
            ->assertSee('Cliente En Espera')
            ->assertSee('Cliente Finalizado')
            ->assertDontSee('No hay citas en esta etapa.')
            ->call('filterGroup', 'waiting')
            ->assertSet('groupFilter', 'waiting')
            ->assertSee('Cliente En Espera')
            ->assertDontSee('Cliente Finalizado')
            ->call('filterGroup', 'waiting')
            ->assertSet('groupFilter', 'all');
    }

    public function test_daily_agenda_can_navigate_to_previous_days_in_read_only_mode(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $pastClient = Client::factory()->create(['first_name' => 'Cliente', 'last_name' => 'Ayer']);
        $todayClient = Client::factory()->create(['first_name' => 'Cliente', 'last_name' => 'Hoy']);
        $yesterday = today()->subDay();

        Appointment::factory()->create([
            'client_id' => $pastClient,
            'starts_at' => $yesterday->copy()->setTime(10, 0),
            'ends_at' => $yesterday->copy()->setTime(10, 30),
            'status' => AppointmentStatus::Pending,
        ]);
        Appointment::factory()->create([
            'client_id' => $todayClient,
            'starts_at' => today()->setTime(11, 0),
            'ends_at' => today()->setTime(11, 30),
            'status' => AppointmentStatus::Pending,
        ]);
        $this->actingAs($administrator);

        Livewire::test(Today::class)
            ->assertSet('selectedDate', today()->toDateString())
            ->assertSee('Cliente Hoy')
            ->call('previousDay')
            ->assertSet('selectedDate', $yesterday->toDateString())
            ->assertSee('Cliente Ayer')
            ->assertDontSee('Cliente Hoy')
            ->assertSee('Consulta histórica en modo de solo lectura.')
            ->assertSee('Solo lectura')
            ->assertDontSee('Cliente sin cita')
            ->call('nextDay')
            ->assertSet('selectedDate', today()->toDateString())
            ->assertSee('Cliente Hoy')
            ->set('selectedDate', $yesterday->toDateString())
            ->call('goToday')
            ->assertSet('selectedDate', today()->toDateString());
    }

    public function test_daily_agenda_displays_total_waiting_and_service_time(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $client = Client::factory()->create(['first_name' => 'Cliente', 'last_name' => 'Con Tiempos']);

        Appointment::factory()->create([
            'client_id' => $client,
            'starts_at' => today()->setTime(10, 0),
            'ends_at' => today()->setTime(10, 30),
            'status' => AppointmentStatus::PendingPayment,
            'arrived_at' => today()->setTime(9, 50),
            'service_started_at' => today()->setTime(10, 0),
            'service_finished_at' => today()->setTime(10, 30),
        ]);
        $this->actingAs($administrator);

        Livewire::test(Today::class)
            ->assertSee('Cliente Con Tiempos')
            ->assertSee('Tiempo de espera')
            ->assertSee('00:10:00')
            ->assertSee('Tiempo de servicio')
            ->assertSee('00:30:00')
            ->assertSee('Tiempo total')
            ->assertSee('00:40:00');
    }

    public function test_administrator_can_force_status_from_secondary_daily_action_and_modifier_is_tracked(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $appointment = Appointment::factory()->create([
            'starts_at' => today()->setTime(10, 0),
            'ends_at' => today()->setTime(10, 30),
            'status' => AppointmentStatus::Pending,
        ]);
        $this->actingAs($administrator);

        Livewire::test(Today::class)
            ->call('forceStatus', $appointment->id, AppointmentStatus::Confirmed->value)
            ->assertHasNoErrors()
            ->assertSee('Confirmada');

        $appointment->refresh();
        $this->assertSame(AppointmentStatus::Confirmed, $appointment->status);
        $this->assertSame($administrator->id, $appointment->updated_by);
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
