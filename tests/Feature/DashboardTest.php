<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\UserRole;
use App\Livewire\Appointments\Today;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Sale;
use App\Models\Service;
use App\Models\User;
use App\Models\WalkInEntry;
use App\Services\CashRegisterService;
use App\Services\OperationalDashboardService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_user_sees_operational_dashboard(): void
    {
        $user = User::factory()->create([
            'name' => 'Ana Barber',
            'role' => UserRole::Barber,
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Dashboard operativo')
            ->assertSee('Citas de hoy')
            ->assertSee('Clientes esperando')
            ->assertSee('Tiempo promedio por barbero')
            ->assertDontSee('Ventas del día');
    }

    public function test_dashboard_calculates_real_operational_metrics(): void
    {
        $now = CarbonImmutable::parse('2026-07-17 12:00:00');
        $this->travelTo($now);
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $barber = Barber::factory()->create(['display_name' => 'Rodrigo Méndez']);
        $service = Service::factory()->create(['duration_minutes' => 30]);
        $client = Client::factory()->create();

        $late = $this->appointment($client, $barber, $service, AppointmentStatus::Confirmed, '09:00');
        $this->appointment($client, $barber, $service, AppointmentStatus::Arrived, '10:00', [
            'arrived_at' => today()->setTime(10, 0),
        ]);
        $this->appointment($client, $barber, $service, AppointmentStatus::InService, '10:30', [
            'service_started_at' => today()->setTime(10, 35),
        ]);
        $this->appointment($client, $barber, $service, AppointmentStatus::PendingPayment, '11:00');
        $completed = $this->appointment($client, $barber, $service, AppointmentStatus::Completed, '08:45', [
            'arrived_at' => today()->setTime(8, 50),
            'service_started_at' => today()->setTime(9, 0),
            'service_finished_at' => today()->setTime(9, 30),
        ]);
        WalkInEntry::factory()->create([
            'arrived_at' => today()->setTime(11, 30),
        ]);

        $cash = app(CashRegisterService::class)->open(500, null, $administrator);
        app(CashRegisterService::class)->recordMovement($cash, 'ingreso', 200, 'Cambio adicional', $administrator);
        app(CashRegisterService::class)->recordMovement($cash, 'gasto', 50, 'Compra operativa', $administrator);
        Sale::query()->create([
            'appointment_id' => $completed->id,
            'client_id' => $client->id,
            'barber_id' => $barber->id,
            'service_id' => $service->id,
            'folio_number' => 1,
            'folio' => 'V-00000001',
            'status' => SaleStatus::Completed,
            'subtotal' => 300,
            'total' => 300,
            'payment_method' => PaymentMethod::Cash,
            'paid_at' => now(),
            'created_by' => $administrator->id,
        ]);

        $metrics = app(OperationalDashboardService::class)->metricsFor($administrator);

        $this->assertSame(5, $metrics['appointmentsToday']);
        $this->assertSame(2, $metrics['waitingClients']);
        $this->assertSame(1, $metrics['servicesInProgress']);
        $this->assertSame(1, $metrics['pendingPayments']);
        $this->assertSame(600, $metrics['averageWaitSeconds']);
        $this->assertSame(1, $metrics['lateAppointments']->count());
        $this->assertSame($late->id, $metrics['lateAppointments']->first()->id);
        $this->assertSame(1800, $metrics['barberPerformance']->first()['average_seconds']);
        $this->assertTrue($metrics['cashIsOpen']);
        $this->assertSame(650.0, $metrics['expectedCash']);
        $this->assertSame(300.0, $metrics['salesToday']);
        $this->assertSame(1, $metrics['salesCount']);
    }

    public function test_barber_dashboard_only_uses_their_appointments_and_hides_financials(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-07-17 12:00:00'));
        $barberUser = User::factory()->create(['role' => UserRole::Barber]);
        $ownBarber = Barber::factory()->for($barberUser)->create(['display_name' => 'Barbero propio']);
        $otherBarber = Barber::factory()->create(['display_name' => 'Barbero ajeno']);
        $service = Service::factory()->create();
        $client = Client::factory()->create();
        $this->appointment($client, $ownBarber, $service, AppointmentStatus::InService, '10:00');
        $this->appointment($client, $otherBarber, $service, AppointmentStatus::PendingPayment, '11:00');

        $metrics = app(OperationalDashboardService::class)->metricsFor($barberUser);

        $this->assertSame(1, $metrics['appointmentsToday']);
        $this->assertSame(1, $metrics['servicesInProgress']);
        $this->assertSame(0, $metrics['pendingPayments']);
        $this->assertFalse($metrics['canSeeFinancials']);
        $this->assertNull($metrics['cashSession']);
        $this->assertSame(0.0, $metrics['salesToday']);
    }

    public function test_dashboard_shortcuts_open_the_requested_daily_group(): void
    {
        $user = User::factory()->create(['role' => UserRole::Receptionist]);
        $this->actingAs($user);

        Livewire::withQueryParams(['group' => 'pending_payment'])
            ->test(Today::class)
            ->assertSet('groupFilter', 'pending_payment');
    }

    /** @param array<string, mixed> $attributes */
    private function appointment(
        Client $client,
        Barber $barber,
        Service $service,
        AppointmentStatus $status,
        string $time,
        array $attributes = [],
    ): Appointment {
        $startsAt = today()->setTimeFromTimeString($time);

        return Appointment::factory()
            ->for($client)
            ->for($barber)
            ->for($service)
            ->create([
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->copy()->addMinutes($service->duration_minutes),
                'status' => $status,
                ...$attributes,
            ]);
    }
}
