<?php

namespace Tests\Feature\Appointments;

use App\Enums\AppointmentStatus;
use App\Enums\CashMovementCategory;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Livewire\Appointments\Today;
use App\Livewire\Sales\ReceiptDelivery;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Service;
use App\Models\User;
use App\Services\AppointmentPaymentService;
use App\Services\CashRegisterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AppointmentPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_registering_payment_creates_sale_cash_movement_commission_and_completes_appointment(): void
    {
        $actor = User::factory()->create(['role' => UserRole::Receptionist]);
        $barber = Barber::factory()->create(['default_commission_percentage' => 15]);
        $service = Service::factory()->create(['commission_percentage' => 25]);
        $cashRegister = app(CashRegisterService::class)->open(500, null, $actor);
        $appointment = Appointment::factory()->for($barber)->for($service)->create([
            'price' => 400,
            'status' => AppointmentStatus::PendingPayment,
        ]);

        $sale = app(AppointmentPaymentService::class)->register($appointment, [
            'payment_method' => PaymentMethod::Card->value,
            'payment_reference' => 'AUT-12345',
        ], $actor);

        $this->assertSame('400.00', $sale->total);
        $this->assertSame(PaymentMethod::Card, $sale->payment_method);
        $this->assertSame('400.00', $sale->cashMovement->amount);
        $this->assertSame(CashMovementCategory::ServiceSale, $sale->cashMovement->category);
        $this->assertSame('25.00', $sale->commission->percentage);
        $this->assertSame('100.00', $sale->commission->amount);
        $this->assertSame(AppointmentStatus::Completed, $appointment->refresh()->status);
        $this->assertSame($actor->id, $appointment->updated_by);

        $this->assertDatabaseHas('sales', ['appointment_id' => $appointment->id]);
        $this->assertDatabaseHas('cash_movements', ['sale_id' => $sale->id, 'type' => 'ingreso']);
        $this->assertSame($cashRegister->id, $sale->cashMovement->cash_register_session_id);
        $this->assertDatabaseHas('commissions', ['sale_id' => $sale->id, 'status' => 'pendiente']);
    }

    public function test_payment_is_rejected_unless_appointment_is_pending_payment(): void
    {
        $actor = User::factory()->create(['role' => UserRole::Receptionist]);
        app(CashRegisterService::class)->open(0, null, $actor);
        $appointment = Appointment::factory()->create(['status' => AppointmentStatus::InService]);

        try {
            app(AppointmentPaymentService::class)->register($appointment, [
                'payment_method' => PaymentMethod::Cash->value,
            ], $actor);
            $this->fail('El cobro anticipado debió ser rechazado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment', $exception->errors());
        }

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('cash_movements', 0);
        $this->assertDatabaseCount('commissions', 0);
    }

    public function test_receptionist_registers_payment_from_daily_modal(): void
    {
        $actor = User::factory()->create(['role' => UserRole::Receptionist]);
        app(CashRegisterService::class)->open(0, null, $actor);
        $appointment = Appointment::factory()->create([
            'starts_at' => today()->setTime(12, 0),
            'ends_at' => today()->setTime(12, 30),
            'price' => 250,
            'status' => AppointmentStatus::PendingPayment,
        ]);

        $this->actingAs($actor);

        Livewire::test(Today::class)
            ->assertSee('Registrar pago')
            ->assertDontSee('Caja cerrada')
            ->call('openPayment', $appointment->id)
            ->assertSet('showPaymentModal', true)
            ->set('payment_method', PaymentMethod::Transfer->value)
            ->set('payment_reference', 'SPEI-987')
            ->call('registerPayment')
            ->assertHasNoErrors()
            ->assertDispatchedTo(ReceiptDelivery::class, 'sale-paid')
            ->assertSet('showPaymentModal', false)
            ->assertSee('Terminada');

        $this->assertDatabaseHas('sales', [
            'appointment_id' => $appointment->id,
            'payment_method' => PaymentMethod::Transfer->value,
            'payment_reference' => 'SPEI-987',
        ]);
        $this->assertSame(AppointmentStatus::Completed, $appointment->refresh()->status);
    }

    public function test_daily_agenda_explains_that_cash_register_must_be_open_before_payment(): void
    {
        $actor = User::factory()->create(['role' => UserRole::Receptionist]);
        Appointment::factory()->create([
            'starts_at' => today()->setTime(12, 0),
            'ends_at' => today()->setTime(12, 30),
            'status' => AppointmentStatus::PendingPayment,
        ]);

        $this->actingAs($actor);

        Livewire::test(Today::class)
            ->assertSee('Caja cerrada')
            ->assertSee('Abrir caja para cobrar')
            ->assertDontSee('Registrar pago');
    }
}
