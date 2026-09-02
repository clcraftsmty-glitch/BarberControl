<?php

namespace Tests\Feature\Clients;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\UserRole;
use App\Livewire\Clients\Show;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Sale;
use App\Models\SaleTicketLog;
use App\Models\Service;
use App\Models\User;
use App\Services\ClientHistoryService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_calculates_visits_habits_incidents_and_valid_spending(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-07-17 12:00:00'));
        $viewer = User::factory()->create(['role' => UserRole::Receptionist]);
        $client = Client::factory()->create(['notes' => 'Prefiere tijera y poco producto.']);
        $usualBarber = Barber::factory()->create(['display_name' => 'Rodrigo Habitual']);
        $otherBarber = Barber::factory()->create(['display_name' => 'Barbero Alterno']);
        $cut = Service::factory()->create(['name' => 'Corte clásico']);
        $beard = Service::factory()->create(['name' => 'Barba premium']);

        $first = $this->appointment($client, $usualBarber, $cut, AppointmentStatus::Completed, 70);
        $second = $this->appointment($client, $usualBarber, $cut, AppointmentStatus::Completed, 40);
        $third = $this->appointment($client, $otherBarber, $beard, AppointmentStatus::Completed, 10);
        $this->appointment($client, $usualBarber, $cut, AppointmentStatus::Cancelled, 5);
        $this->appointment($client, $otherBarber, $beard, AppointmentStatus::NoShow, 3);

        $firstSale = $this->sale($first, $viewer, SaleStatus::Completed, 200, 1);
        $this->sale($second, $viewer, SaleStatus::Completed, 300, 2);
        $this->sale($third, $viewer, SaleStatus::Cancelled, 400, 3);
        SaleTicketLog::query()->create([
            'sale_id' => $firstSale->id,
            'action' => 'impresion',
            'created_by' => $viewer->id,
        ]);

        $history = app(ClientHistoryService::class)->forClient($client, $viewer);

        $this->assertSame(3, $history['completedVisitCount']);
        $this->assertSame(30, $history['averageVisitIntervalDays']);
        $this->assertSame(10, $history['daysSinceLastVisit']);
        $this->assertSame('Rodrigo Habitual', $history['usualBarber']['name']);
        $this->assertSame(2, $history['usualBarber']['visits']);
        $this->assertSame('Corte clásico', $history['frequentServices']->first()['name']);
        $this->assertSame(2, $history['frequentServices']->first()['visits']);
        $this->assertSame(67, $history['frequentServices']->first()['percentage']);
        $this->assertSame(1, $history['cancelledCount']);
        $this->assertSame(1, $history['noShowCount']);
        $this->assertSame(500.0, $history['totalSpent']);
        $this->assertSame(2, $history['paymentCount']);
        $this->assertCount(3, $history['sales']);
    }

    public function test_receptionist_sees_complete_history_tickets_and_preferences(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-07-17 12:00:00'));
        $viewer = User::factory()->create(['role' => UserRole::Receptionist]);
        $client = Client::factory()->create([
            'first_name' => 'Lucía',
            'last_name' => 'Frecuente',
            'notes' => 'Alérgica a productos con fragancia.',
        ]);
        $barber = Barber::factory()->create(['display_name' => 'Carlos Preferido']);
        $service = Service::factory()->create(['name' => 'Corte ejecutivo']);
        $appointment = $this->appointment($client, $barber, $service, AppointmentStatus::Completed, 7);
        $sale = $this->sale($appointment, $viewer, SaleStatus::Completed, 275, 1);

        $this->actingAs($viewer);

        Livewire::test(Show::class, ['client' => $client])
            ->assertSee('Visitas completadas')
            ->assertSee('Lucía Frecuente')
            ->assertSee('Corte ejecutivo')
            ->assertSee('Carlos Preferido')
            ->assertSee('Hace 7 días')
            ->assertSee('Alérgica a productos con fragancia.')
            ->assertSee('Total gastado')
            ->assertSee('$275.00')
            ->assertSee('Tickets y pagos')
            ->assertSee($sale->folio)
            ->assertSee('PDF')
            ->assertSee('Imprimir');
    }

    public function test_barber_sees_service_history_but_not_financial_information(): void
    {
        $barberUser = User::factory()->create(['role' => UserRole::Barber]);
        $client = Client::factory()->create(['notes' => 'Usar máquina número dos.']);
        $barber = Barber::factory()->for($barberUser)->create(['display_name' => 'Barbero de prueba']);
        $service = Service::factory()->create(['name' => 'Desvanecido']);
        $appointment = $this->appointment($client, $barber, $service, AppointmentStatus::Completed, 2);
        $this->sale($appointment, User::factory()->create(['role' => UserRole::Receptionist]), SaleStatus::Completed, 350, 1);

        $this->actingAs($barberUser);

        Livewire::test(Show::class, ['client' => $client])
            ->assertSee('Desvanecido')
            ->assertSee('Barbero de prueba')
            ->assertSee('Usar máquina número dos.')
            ->assertDontSee('Total gastado')
            ->assertDontSee('Tickets y pagos')
            ->assertDontSee('$350.00');
    }

    public function test_empty_history_has_clear_first_visit_states(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Receptionist]);
        $client = Client::factory()->create();
        $this->actingAs($viewer);

        Livewire::test(Show::class, ['client' => $client])
            ->assertSee('Sin visitas')
            ->assertSee('Sin promedio')
            ->assertSee('Sin definir')
            ->assertSee('Sin servicios terminados')
            ->assertSee('Este cliente todavía no tiene citas')
            ->assertSee('No hay pagos registrados');
    }

    private function appointment(
        Client $client,
        Barber $barber,
        Service $service,
        AppointmentStatus $status,
        int $daysAgo,
    ): Appointment {
        $startsAt = now()->subDays($daysAgo)->setTime(10, 0);

        return Appointment::factory()
            ->for($client)
            ->for($barber)
            ->for($service)
            ->create([
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->copy()->addMinutes($service->duration_minutes),
                'status' => $status,
            ]);
    }

    private function sale(
        Appointment $appointment,
        User $actor,
        SaleStatus $status,
        float $total,
        int $folio,
    ): Sale {
        return Sale::query()->create([
            'folio_number' => $folio,
            'folio' => sprintf('V-%08d', $folio),
            'status' => $status,
            'appointment_id' => $appointment->id,
            'client_id' => $appointment->client_id,
            'barber_id' => $appointment->barber_id,
            'service_id' => $appointment->service_id,
            'subtotal' => $total,
            'total' => $total,
            'payment_method' => PaymentMethod::Cash,
            'paid_at' => $appointment->starts_at,
            'created_by' => $actor->id,
            'client_name_snapshot' => $appointment->client->full_name,
            'client_phone_snapshot' => $appointment->client->phone,
            'barber_name_snapshot' => $appointment->barber->display_name,
            'service_name_snapshot' => $appointment->service->name,
            'service_duration_minutes_snapshot' => $appointment->service->duration_minutes,
            'unit_price_snapshot' => $total,
        ]);
    }
}
