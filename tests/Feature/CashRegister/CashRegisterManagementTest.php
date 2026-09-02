<?php

namespace Tests\Feature\CashRegister;

use App\Enums\AppointmentStatus;
use App\Enums\CashRegisterStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Livewire\CashRegister\Dashboard;
use App\Models\Appointment;
use App\Models\User;
use App\Services\AppointmentPaymentService;
use App\Services\CashRegisterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CashRegisterManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_income_expense_and_close_calculate_cash_difference(): void
    {
        $actor = User::factory()->create(['role' => UserRole::Administrator]);
        $service = app(CashRegisterService::class);
        $session = $service->open(500, 'Fondo inicial', $actor);

        $service->recordMovement($session, 'ingreso', 100, 'Venta mostrador', $actor);
        $service->recordMovement($session, 'gasto', 40, 'Compra de insumos', $actor);

        $this->assertSame(560.0, $session->expectedCashNow());

        $closed = $service->close($session, 550, 'Faltante revisado', $actor, 'Faltante confirmado en arqueo');

        $this->assertSame(CashRegisterStatus::Closed, $closed->status);
        $this->assertSame('560.00', $closed->expected_cash);
        $this->assertSame('550.00', $closed->actual_cash);
        $this->assertSame('-10.00', $closed->difference);
        $this->assertSame($actor->id, $closed->difference_authorized_by);
        $this->assertNull($closed->open_guard);
    }

    public function test_only_one_cash_register_can_be_open(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $receptionist = User::factory()->create(['role' => UserRole::Receptionist]);
        $service = app(CashRegisterService::class);
        $service->open(0, null, $administrator);

        $this->expectException(ValidationException::class);
        $service->open(0, null, $receptionist);
    }

    public function test_service_payments_belong_to_open_register_and_only_cash_affects_expected_cash(): void
    {
        $actor = User::factory()->create(['role' => UserRole::Receptionist]);
        $session = app(CashRegisterService::class)->open(200, null, $actor);
        $cashAppointment = Appointment::factory()->create([
            'price' => 300,
            'status' => AppointmentStatus::PendingPayment,
        ]);
        $cardAppointment = Appointment::factory()->create([
            'price' => 400,
            'status' => AppointmentStatus::PendingPayment,
        ]);
        $payments = app(AppointmentPaymentService::class);

        $cashSale = $payments->register($cashAppointment, [
            'payment_method' => PaymentMethod::Cash->value,
        ], $actor);
        $cardSale = $payments->register($cardAppointment, [
            'payment_method' => PaymentMethod::Card->value,
        ], $actor);

        $this->assertSame($session->id, $cashSale->cashMovement->cash_register_session_id);
        $this->assertSame($session->id, $cardSale->cashMovement->cash_register_session_id);
        $this->assertSame(500.0, $session->expectedCashNow());
        $this->assertDatabaseCount('cash_movements', 2);
    }

    public function test_administrator_operates_register_from_livewire_screen(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $this->actingAs($administrator);

        Livewire::test(Dashboard::class)
            ->call('openRegisterModal')
            ->set('opening_amount', '100.00')
            ->call('openRegister')
            ->assertHasNoErrors()
            ->assertSee('Caja abierta')
            ->call('openMovementModal', 'ingreso')
            ->set('movement_amount', '25.00')
            ->set('movement_description', 'Ingreso de prueba')
            ->call('recordMovement')
            ->assertHasNoErrors()
            ->assertSee('Ingreso de prueba')
            ->call('openCloseModal')
            ->set('actual_cash', '120.00')
            ->set('difference_reason', 'Faltante verificado por administrador')
            ->call('closeRegister')
            ->assertHasNoErrors()
            ->assertSee('No hay una caja abierta')
            ->assertSee('−$5.00');

        $this->assertDatabaseHas('cash_register_sessions', [
            'status' => CashRegisterStatus::Closed->value,
            'expected_cash' => 125,
            'actual_cash' => 120,
            'difference' => -5,
        ]);
    }

    public function test_payment_is_blocked_without_open_register(): void
    {
        $actor = User::factory()->create(['role' => UserRole::Receptionist]);
        $appointment = Appointment::factory()->create(['status' => AppointmentStatus::PendingPayment]);

        try {
            app(AppointmentPaymentService::class)->register($appointment, [
                'payment_method' => PaymentMethod::Cash->value,
            ], $actor);
            $this->fail('El cobro debió requerir una caja abierta.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Debes abrir la caja antes de registrar un cobro.',
                $exception->errors()['payment'][0],
            );
        }

        $this->assertDatabaseCount('sales', 0);
    }
}
