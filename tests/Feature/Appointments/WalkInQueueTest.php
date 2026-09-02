<?php

namespace Tests\Feature\Appointments;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Enums\WalkInStatus;
use App\Livewire\Appointments\Today;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use App\Models\WalkInEntry;
use App\Services\WalkInQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class WalkInQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_receptionist_adds_an_existing_client_to_the_walk_in_queue(): void
    {
        Carbon::setTestNow('2026-07-16 10:00:00');
        $actor = User::factory()->create(['role' => UserRole::Receptionist]);
        $client = Client::factory()->create();
        $service = Service::factory()->create();

        $this->actingAs($actor);

        Livewire::test(Today::class)
            ->call('openWalkIn')
            ->assertSet('showWalkInModal', true)
            ->set('walkInClientId', (string) $client->id)
            ->set('walkInServiceId', (string) $service->id)
            ->call('registerWalkIn')
            ->assertHasNoErrors()
            ->assertSet('showWalkInModal', false)
            ->assertSee($client->full_name)
            ->assertSee('Tiempo en fila');

        $this->assertDatabaseHas('walk_in_entries', [
            'client_id' => $client->id,
            'service_id' => $service->id,
            'status' => WalkInStatus::Waiting->value,
            'created_by' => $actor->id,
        ]);
    }

    public function test_receptionist_can_create_a_quick_client_when_registering_a_walk_in(): void
    {
        $actor = User::factory()->create(['role' => UserRole::Receptionist]);
        $service = Service::factory()->create();
        $this->actingAs($actor);

        Livewire::test(Today::class)
            ->call('openWalkIn')
            ->set('walkInCreateClient', true)
            ->set('walkInFirstName', 'María')
            ->set('walkInLastName', 'Sin Cita')
            ->set('walkInPhone', '5512345678')
            ->set('walkInServiceId', (string) $service->id)
            ->call('registerWalkIn')
            ->assertHasNoErrors();

        $client = Client::query()->where('phone', '5512345678')->firstOrFail();
        $this->assertDatabaseHas('walk_in_entries', [
            'client_id' => $client->id,
            'status' => WalkInStatus::Waiting->value,
        ]);
    }

    public function test_walk_in_client_search_selects_exact_phone_and_marks_clients_already_waiting(): void
    {
        $actor = User::factory()->create(['role' => UserRole::Receptionist]);
        $availableClient = Client::factory()->create([
            'first_name' => 'Cliente',
            'last_name' => 'Disponible',
            'phone' => '8111111111',
        ]);
        $waitingClient = Client::factory()->create([
            'first_name' => 'Cliente',
            'last_name' => 'En Fila',
            'phone' => '8222222222',
        ]);
        WalkInEntry::factory()->for($waitingClient)->create();
        $this->actingAs($actor);

        Livewire::test(Today::class)
            ->call('openWalkIn')
            ->set('walkInClientSearch', '8111111111')
            ->assertSet('walkInClientId', (string) $availableClient->id)
            ->assertSee('Cliente seleccionado')
            ->call('clearWalkInClient')
            ->set('walkInClientSearch', '8222222222')
            ->assertSet('walkInClientId', '')
            ->assertSee('Cliente En Fila')
            ->assertSee('Ya está en fila');
    }

    public function test_walk_in_form_shows_clear_spanish_validation_messages(): void
    {
        $actor = User::factory()->create(['role' => UserRole::Receptionist]);
        $this->actingAs($actor);

        Livewire::test(Today::class)
            ->call('openWalkIn')
            ->call('registerWalkIn')
            ->assertHasErrors(['walkInClientId', 'walkInServiceId'])
            ->assertSee('Busca y selecciona un cliente.')
            ->assertSee('Selecciona el servicio solicitado.');
    }

    public function test_starting_a_walk_in_creates_an_in_service_appointment_with_real_wait_time(): void
    {
        Carbon::setTestNow('2026-07-16 10:00:00');
        $actor = User::factory()->create(['role' => UserRole::Receptionist]);
        $barber = Barber::factory()->create();
        $service = Service::factory()->create(['duration_minutes' => 30, 'price' => 250]);
        $barber->services()->attach($service);
        $entry = WalkInEntry::factory()->for($service)->create([
            'arrived_at' => now()->subMinutes(15),
            'status' => WalkInStatus::Waiting,
        ]);

        $appointment = app(WalkInQueueService::class)->start($entry, $barber, $actor);

        $this->assertSame(AppointmentStatus::InService, $appointment->status);
        $this->assertSame(AppointmentSource::WalkIn, $appointment->source);
        $this->assertSame('250.00', $appointment->price);
        $this->assertSame(900, $appointment->waitingDurationSeconds());
        $this->assertTrue($appointment->service_started_at->equalTo(now()));
        $this->assertTrue($appointment->ends_at->equalTo(now()->addMinutes(30)->seconds(0)));

        $entry->refresh();
        $this->assertSame(WalkInStatus::Converted, $entry->status);
        $this->assertSame($barber->id, $entry->assigned_barber_id);
        $this->assertSame($appointment->id, $entry->appointment_id);
    }

    public function test_wait_estimate_considers_upcoming_appointments_and_allows_a_free_barber(): void
    {
        Carbon::setTestNow('2026-07-16 10:00:00');
        $actor = User::factory()->create(['role' => UserRole::Receptionist]);
        $busyBarber = Barber::factory()->create(['display_name' => 'Barbero ocupado']);
        $freeBarber = Barber::factory()->create(['display_name' => 'Barbero disponible']);
        $service = Service::factory()->create(['duration_minutes' => 30]);
        $busyBarber->services()->attach($service);
        $freeBarber->services()->attach($service);
        $entry = WalkInEntry::factory()->for($service)->create([
            'preferred_barber_id' => $busyBarber->id,
            'arrived_at' => now(),
        ]);
        Appointment::factory()->for($busyBarber)->create([
            'starts_at' => now()->addMinutes(15),
            'ends_at' => now()->addMinutes(45),
            'status' => AppointmentStatus::Confirmed,
        ]);
        $this->actingAs($actor);

        Livewire::test(Today::class)
            ->assertSee('Espera estimada: ~45 min')
            ->assertSee('Barbero ocupado')
            ->assertSee('disponible en ~45 min')
            ->assertSee('Barbero disponible')
            ->assertSee('disponible ahora')
            ->assertSee('Disponible en ~45 min')
            ->set("walkInBarberSelections.{$entry->id}", (string) $freeBarber->id)
            ->assertSee('Iniciar servicio')
            ->call('startWalkIn', $entry->id)
            ->assertHasNoErrors();

        $this->assertSame(WalkInStatus::Converted, $entry->refresh()->status);
        $this->assertDatabaseHas('appointments', [
            'barber_id' => $freeBarber->id,
            'source' => AppointmentSource::WalkIn->value,
            'status' => AppointmentStatus::InService->value,
        ]);
    }

    public function test_walk_in_can_start_before_a_future_appointment_when_the_service_fits(): void
    {
        Carbon::setTestNow('2026-07-16 10:00:00');
        $actor = User::factory()->create(['role' => UserRole::Receptionist]);
        $barber = Barber::factory()->create();
        $service = Service::factory()->create(['duration_minutes' => 30]);
        $barber->services()->attach($service);
        $entry = WalkInEntry::factory()->for($service)->create(['preferred_barber_id' => $barber->id]);
        Appointment::factory()->for($barber)->create([
            'starts_at' => now()->addMinutes(30),
            'ends_at' => now()->addMinutes(60),
            'status' => AppointmentStatus::Confirmed,
        ]);
        $this->actingAs($actor);

        Livewire::test(Today::class)
            ->assertSee('Espera estimada: ~0 min')
            ->assertSee('disponible ahora')
            ->call('startWalkIn', $entry->id)
            ->assertHasNoErrors();

        $this->assertSame(WalkInStatus::Converted, $entry->refresh()->status);
    }

    public function test_walk_in_cannot_start_when_barber_has_an_overlapping_appointment(): void
    {
        Carbon::setTestNow('2026-07-16 10:00:00');
        $actor = User::factory()->create(['role' => UserRole::Receptionist]);
        $barber = Barber::factory()->create();
        $service = Service::factory()->create(['duration_minutes' => 30]);
        $barber->services()->attach($service);
        $entry = WalkInEntry::factory()->for($service)->create();
        Appointment::factory()->for($barber)->create([
            'starts_at' => now()->subMinutes(10),
            'ends_at' => now()->addMinutes(20),
            'status' => AppointmentStatus::Confirmed,
        ]);

        try {
            app(WalkInQueueService::class)->start($entry, $barber, $actor);
            $this->fail('No se debe iniciar un servicio cuando el barbero está ocupado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('schedule.overlap', $exception->errors());
        }

        $this->assertSame(WalkInStatus::Waiting, $entry->refresh()->status);
        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_overdue_active_service_keeps_barber_busy_and_blocks_another_walk_in(): void
    {
        Carbon::setTestNow('2026-07-17 15:08:00');
        $actor = User::factory()->create(['role' => UserRole::Receptionist]);
        $barber = Barber::factory()->create(['display_name' => 'BARBER 2']);
        $service = Service::factory()->create(['duration_minutes' => 30]);
        $barber->services()->attach($service);
        $entry = WalkInEntry::factory()->for($service)->create([
            'preferred_barber_id' => $barber->id,
        ]);
        Appointment::factory()->for($barber)->create([
            'starts_at' => now()->subMinutes(46),
            'ends_at' => now()->subMinutes(16),
            'status' => AppointmentStatus::InService,
            'service_started_at' => now()->subMinutes(45),
        ]);
        $this->actingAs($actor);

        Livewire::test(Today::class)
            ->assertSee('en servicio ahora')
            ->assertSee('Barbero en servicio')
            ->call('startWalkIn', $entry->id)
            ->assertHasErrors(["walkInBarberSelections.{$entry->id}"])
            ->assertSee('BARBER 2 ya tiene un servicio en curso.');

        $this->assertSame(WalkInStatus::Waiting, $entry->refresh()->status);
        $this->assertDatabaseCount('appointments', 1);
        Carbon::setTestNow();
    }

    public function test_receptionist_marks_that_a_waiting_client_left(): void
    {
        $actor = User::factory()->create(['role' => UserRole::Receptionist]);
        $entry = WalkInEntry::factory()->create();

        $entry = app(WalkInQueueService::class)->markLeft($entry, $actor);

        $this->assertSame(WalkInStatus::Left, $entry->status);
        $this->assertNotNull($entry->left_at);
        $this->assertSame($actor->id, $entry->updated_by);
    }

    public function test_daily_agenda_displays_withdrawn_clients_for_the_selected_date(): void
    {
        Carbon::setTestNow('2026-07-16 16:00:00');
        $actor = User::factory()->create(['role' => UserRole::Receptionist]);
        $todayClient = Client::factory()->create(['first_name' => 'Retirado', 'last_name' => 'Hoy']);
        $pastClient = Client::factory()->create(['first_name' => 'Retirado', 'last_name' => 'Ayer']);
        $yesterday = now()->subDay();

        WalkInEntry::factory()->for($todayClient)->create([
            'status' => WalkInStatus::Left,
            'arrived_at' => now()->subMinutes(25),
            'left_at' => now()->subMinutes(5),
        ]);
        WalkInEntry::factory()->for($pastClient)->create([
            'status' => WalkInStatus::Left,
            'arrived_at' => $yesterday->copy()->setTime(12, 0),
            'left_at' => $yesterday->copy()->setTime(12, 15),
        ]);
        $this->actingAs($actor);

        Livewire::test(Today::class)
            ->assertSee('Retirados')
            ->assertSee('Retirado Hoy')
            ->assertSee('Espera antes de retirarse')
            ->assertSee('00:20:00')
            ->assertDontSee('Retirado Ayer')
            ->set('selectedDate', $yesterday->toDateString())
            ->assertSee('Retirado Ayer')
            ->assertSee('00:15:00')
            ->assertDontSee('Retirado Hoy');
    }
}
