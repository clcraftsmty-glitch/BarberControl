<?php

namespace Tests\Feature\Sales;

use App\Enums\AppointmentStatus;
use App\Enums\CashMovementCategory;
use App\Enums\CommissionStatus;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\UserRole;
use App\Livewire\Sales\Index;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Sale;
use App\Models\Service;
use App\Models\User;
use App\Services\AppointmentPaymentService;
use App\Services\CashRegisterService;
use App\Services\SaleAdjustmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SalesManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_payments_receive_consecutive_folios_and_historical_snapshots(): void
    {
        $actor = User::factory()->create(['role' => UserRole::Receptionist]);
        app(CashRegisterService::class)->open(0, null, $actor);

        $client = Client::factory()->create([
            'first_name' => 'Luis',
            'last_name' => 'Histórico',
            'phone' => '5512345678',
        ]);
        $barber = Barber::factory()->create(['display_name' => 'Barbero Original']);
        $service = Service::factory()->create([
            'name' => 'Corte Original',
            'description' => 'Servicio guardado',
            'duration_minutes' => 45,
            'price' => 350,
            'commission_percentage' => 20,
        ]);

        $first = $this->pay($actor, $client, $barber, $service, 350);
        $second = $this->pay($actor, Client::factory()->create(), $barber, $service, 400);

        $this->assertSame('V-00000001', $first->folio);
        $this->assertSame('V-00000002', $second->folio);
        $this->assertSame('Luis Histórico', $first->client_name_snapshot);
        $this->assertSame('Corte Original', $first->service_name_snapshot);
        $this->assertSame('Barbero Original', $first->barber_name_snapshot);
        $this->assertSame('350.00', $first->unit_price_snapshot);

        $client->update(['first_name' => 'Nombre cambiado']);
        $service->update(['name' => 'Servicio cambiado', 'price' => 999]);
        $barber->update(['display_name' => 'Barbero cambiado']);

        $first->refresh();
        $this->assertSame('Luis Histórico', $first->client_name_snapshot);
        $this->assertSame('Corte Original', $first->service_name_snapshot);
        $this->assertSame('Barbero Original', $first->barber_name_snapshot);
        $this->assertSame('350.00', $first->unit_price_snapshot);
    }

    public function test_history_can_search_by_folio_client_and_phone_and_filter_dates(): void
    {
        $actor = User::factory()->create(['role' => UserRole::Receptionist]);
        app(CashRegisterService::class)->open(0, null, $actor);
        $match = $this->pay(
            $actor,
            Client::factory()->create(['first_name' => 'María', 'last_name' => 'Buscada', 'phone' => '5588881122']),
            Barber::factory()->create(),
            Service::factory()->create(['name' => 'Corte búsqueda']),
            280,
        );
        $this->pay($actor, Client::factory()->create(['first_name' => 'Otro', 'last_name' => 'Cliente']), Barber::factory()->create(), Service::factory()->create(), 190);
        $this->actingAs($actor);

        Livewire::test(Index::class)
            ->set('search', $match->folio)
            ->assertSee('María Buscada')
            ->assertDontSee('Otro Cliente')
            ->set('search', '5588881122')
            ->assertSee($match->folio)
            ->set('search', 'María')
            ->assertSee($match->folio)
            ->set('dateFrom', today()->addDay()->toDateString())
            ->assertDontSee($match->folio)
            ->call('clearFilters')
            ->assertSee($match->folio);
    }

    public function test_administrator_can_cancel_sale_and_reversal_is_recorded(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        app(CashRegisterService::class)->open(500, null, $administrator);
        $sale = $this->pay($administrator, Client::factory()->create(), Barber::factory()->create(), Service::factory()->create(), 300);

        $cancelled = app(SaleAdjustmentService::class)->cancel($sale, 'Cobro duplicado confirmado', $administrator);

        $this->assertSame(SaleStatus::Cancelled, $cancelled->status);
        $this->assertSame('300.00', $cancelled->refunded_amount);
        $this->assertSame(CommissionStatus::Cancelled, $cancelled->commission->status);
        $this->assertCount(2, $cancelled->cashMovements);
        $this->assertDatabaseHas('cash_movements', [
            'sale_id' => $sale->id,
            'type' => 'gasto',
            'category' => CashMovementCategory::Refund->value,
            'amount' => 300,
        ]);
        $this->assertSame(500.0, $cancelled->cashMovements->first()->cashRegisterSession->expectedCashNow());
    }

    public function test_administrator_can_register_refund_from_history(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        app(CashRegisterService::class)->open(0, null, $administrator);
        $sale = $this->pay($administrator, Client::factory()->create(), Barber::factory()->create(), Service::factory()->create(), 225);
        $this->actingAs($administrator);

        Livewire::test(Index::class)
            ->call('openAdjustment', $sale->id, 'refund')
            ->assertSet('showAdjustmentModal', true)
            ->set('adjustment_reason', 'Cliente inconforme, devolución autorizada')
            ->call('applyAdjustment')
            ->assertHasNoErrors()
            ->assertSee('Devuelta');

        $this->assertSame(SaleStatus::Refunded, $sale->refresh()->status);
        $this->assertNotNull($sale->refunded_at);
    }

    public function test_receptionist_cannot_cancel_or_refund_sales(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $receptionist = User::factory()->create(['role' => UserRole::Receptionist]);
        app(CashRegisterService::class)->open(0, null, $administrator);
        $sale = $this->pay($administrator, Client::factory()->create(), Barber::factory()->create(), Service::factory()->create(), 200);

        $this->assertFalse($receptionist->can('cancel', $sale));
        $this->assertFalse($receptionist->can('refund', $sale));
        $this->assertTrue($receptionist->can('view', $sale));
        $this->assertTrue($receptionist->can('print', $sale));
    }

    public function test_ticket_can_be_printed_and_downloaded_as_pdf_with_audit_log(): void
    {
        $actor = User::factory()->create(['role' => UserRole::Receptionist]);
        app(CashRegisterService::class)->open(0, null, $actor);
        $sale = $this->pay($actor, Client::factory()->create(), Barber::factory()->create(), Service::factory()->create(), 250);

        $print = $this->actingAs($actor)->get(route('sales.ticket.print', $sale));
        $print->assertOk()->assertSee($sale->folio)->assertSee('Imprimir ticket');

        $pdf = $this->actingAs($actor)->get(route('sales.ticket.pdf', $sale));
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-1.4', $pdf->getContent());
        $this->assertStringContainsString($sale->folio, $pdf->getContent());
        $this->assertDatabaseHas('sale_ticket_logs', ['sale_id' => $sale->id, 'action' => 'impresion']);
        $this->assertDatabaseHas('sale_ticket_logs', ['sale_id' => $sale->id, 'action' => 'pdf']);
    }

    private function pay(User $actor, Client $client, Barber $barber, Service $service, float $price): Sale
    {
        $appointment = Appointment::factory()->for($client)->for($barber)->for($service)->create([
            'price' => $price,
            'status' => AppointmentStatus::PendingPayment,
        ]);

        return app(AppointmentPaymentService::class)->register($appointment, [
            'payment_method' => PaymentMethod::Cash->value,
        ], $actor);
    }
}
