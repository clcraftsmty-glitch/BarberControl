<?php

namespace Tests\Feature\Commissions;

use App\Enums\AppointmentStatus;
use App\Enums\CashMovementCategory;
use App\Enums\CommissionAdjustmentStatus;
use App\Enums\CommissionAdjustmentType;
use App\Enums\CommissionPeriod;
use App\Enums\CommissionStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Livewire\Commissions\Index;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BusinessSetting;
use App\Models\Client;
use App\Models\Sale;
use App\Models\Service;
use App\Models\User;
use App\Services\AppointmentPaymentService;
use App\Services\CashRegisterService;
use App\Services\CommissionSettlementService;
use App\Services\SaleAdjustmentService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CommissionSettlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_settle_period_with_service_breakdown_and_authorized_adjustments(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        app(CashRegisterService::class)->open(0, null, $administrator);
        $barber = Barber::factory()->create();
        $service = Service::factory()->create(['commission_percentage' => 20]);
        $first = $this->pay($administrator, $barber, $service, 500, '2026-07-13 10:00:00');
        $second = $this->pay($administrator, $barber, $service, 250, '2026-07-17 11:00:00');
        $outside = $this->pay($administrator, $barber, $service, 300, '2026-07-20 12:00:00');
        $settlements = app(CommissionSettlementService::class);
        $adjustment = $settlements->createAdjustment(
            $barber,
            CommissionAdjustmentType::Credit,
            25,
            'Bono autorizado por productividad',
            $administrator,
        );

        $settlement = $settlements->settle(
            $barber,
            CommissionPeriod::Weekly,
            CarbonImmutable::parse('2026-07-13'),
            CarbonImmutable::parse('2026-07-19'),
            PaymentMethod::Transfer,
            'TRX-1001',
            'Liquidación semanal',
            $administrator,
        );

        $this->assertSame('LC-00000001', $settlement->folio);
        $this->assertSame('150.00', $settlement->commissions_total);
        $this->assertSame('25.00', $settlement->adjustments_total);
        $this->assertSame('175.00', $settlement->total_paid);
        $this->assertCount(2, $settlement->commissions);
        $this->assertTrue($settlement->commissions->pluck('sale_id')->contains($first->id));
        $this->assertTrue($settlement->commissions->pluck('sale_id')->contains($second->id));
        $this->assertSame(CommissionStatus::Pending, $outside->commission->refresh()->status);
        $this->assertSame(CommissionAdjustmentStatus::Applied, $adjustment->refresh()->status);
        $this->assertDatabaseHas('commissions', [
            'sale_id' => $first->id,
            'commission_settlement_id' => $settlement->id,
            'status' => CommissionStatus::Paid->value,
            'paid_by' => $administrator->id,
        ]);
        $this->assertDatabaseHas('cash_movements', [
            'commission_settlement_id' => $settlement->id,
            'type' => 'gasto',
            'category' => CashMovementCategory::CommissionPayment->value,
            'amount' => 175,
            'payment_method' => PaymentMethod::Transfer->value,
        ]);
    }

    public function test_biweekly_period_only_includes_sales_from_selected_fortnight(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        app(CashRegisterService::class)->open(0, null, $administrator);
        $barber = Barber::factory()->create();
        $service = Service::factory()->create(['commission_percentage' => 10]);
        $included = $this->pay($administrator, $barber, $service, 400, '2026-07-15 10:00:00');
        $excluded = $this->pay($administrator, $barber, $service, 600, '2026-07-16 10:00:00');

        $settlement = app(CommissionSettlementService::class)->settle(
            $barber,
            CommissionPeriod::Biweekly,
            CarbonImmutable::parse('2026-07-01'),
            CarbonImmutable::parse('2026-07-15'),
            PaymentMethod::Cash,
            null,
            null,
            $administrator,
        );

        $this->assertCount(1, $settlement->commissions);
        $this->assertSame($included->id, $settlement->commissions->first()->sale_id);
        $this->assertSame('40.00', $settlement->total_paid);
        $this->assertSame(CommissionStatus::Pending, $excluded->commission->refresh()->status);
        $this->assertDatabaseHas('cash_movements', [
            'type' => 'gasto',
            'category' => CashMovementCategory::CommissionPayment->value,
            'amount' => 40,
            'payment_method' => PaymentMethod::Cash->value,
        ]);
    }

    public function test_only_administrator_can_create_adjustments_or_mark_commissions_as_paid(): void
    {
        $receptionist = User::factory()->create(['role' => UserRole::Receptionist]);
        $barber = Barber::factory()->create();
        $service = app(CommissionSettlementService::class);

        $this->expectException(AuthorizationException::class);

        $service->createAdjustment(
            $barber,
            CommissionAdjustmentType::Debit,
            10,
            'Descuento no autorizado',
            $receptionist,
        );
    }

    public function test_cancelling_a_sale_after_commission_payment_preserves_history_and_creates_debit(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        app(CashRegisterService::class)->open(0, null, $administrator);
        $barber = Barber::factory()->create();
        $service = Service::factory()->create(['commission_percentage' => 20]);
        $sale = $this->pay($administrator, $barber, $service, 500, '2026-07-14 10:00:00');
        app(CommissionSettlementService::class)->settle(
            $barber,
            CommissionPeriod::Weekly,
            CarbonImmutable::parse('2026-07-13'),
            CarbonImmutable::parse('2026-07-19'),
            PaymentMethod::Transfer,
            null,
            null,
            $administrator,
        );

        app(SaleAdjustmentService::class)->cancel($sale, 'Venta duplicada confirmada', $administrator);

        $this->assertSame(CommissionStatus::Paid, $sale->commission->refresh()->status);
        $this->assertDatabaseHas('commission_adjustments', [
            'barber_id' => $barber->id,
            'type' => CommissionAdjustmentType::Debit->value,
            'amount' => 100,
            'status' => CommissionAdjustmentStatus::Pending->value,
            'authorized_by' => $administrator->id,
        ]);
    }

    public function test_receipt_can_be_printed_and_downloaded_and_barber_only_sees_own_receipts(): void
    {
        Storage::fake('public');
        $logo = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
        Storage::disk('public')->put('business-logos/report.png', $logo);
        BusinessSetting::current()->update([
            'business_name' => 'Barbería Real',
            'logo_path' => 'business-logos/report.png',
        ]);
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        app(CashRegisterService::class)->open(0, null, $administrator);
        $barber = Barber::factory()->create();
        $service = Service::factory()->create(['commission_percentage' => 15]);
        $sale = $this->pay($administrator, $barber, $service, 200, '2026-07-14 10:00:00');
        $settlement = app(CommissionSettlementService::class)->settle(
            $barber,
            CommissionPeriod::Weekly,
            CarbonImmutable::parse('2026-07-13'),
            CarbonImmutable::parse('2026-07-19'),
            PaymentMethod::Transfer,
            'TRX-2002',
            null,
            $administrator,
        );

        $this->actingAs($barber->user)
            ->get(route('commissions.receipt', $settlement))
            ->assertOk()
            ->assertSee($settlement->folio)
            ->assertSee($sale->folio)
            ->assertSee('BARBERÍA REAL')
            ->assertSee('/branding/logo?v=', false)
            ->assertSee('size:Letter portrait', false)
            ->assertSee('Servicios liquidados en dos columnas');
        $pdf = $this->get(route('commissions.receipt.pdf', $settlement));
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-1.4', $pdf->getContent());
        $this->assertStringContainsString($settlement->folio, $pdf->getContent());
        $this->assertStringContainsString('/MediaBox [0 0 612.00 792.00]', $pdf->getContent());
        $this->assertStringContainsString('/Logo Do', $pdf->getContent());
        $this->assertStringContainsString('/Subtype /Image', $pdf->getContent());

        $otherBarber = Barber::factory()->create();
        $this->actingAs($otherBarber->user)
            ->get(route('commissions.receipt', $settlement))
            ->assertForbidden();
    }

    public function test_commission_screen_shows_pending_services_and_history(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        app(CashRegisterService::class)->open(0, null, $administrator);
        $barber = Barber::factory()->create(['display_name' => 'Barbero Comisión']);
        $service = Service::factory()->create(['name' => 'Corte Ejecutivo', 'commission_percentage' => 20]);
        $sale = $this->pay($administrator, $barber, $service, 300, now()->toDateTimeString());
        $this->actingAs($administrator);

        Livewire::test(Index::class)
            ->assertSee('Barbero Comisión')
            ->assertSee('Corte Ejecutivo')
            ->call('openSettlement', $barber->id)
            ->assertSet('showSettlementModal', true)
            ->set('paymentMethod', PaymentMethod::Transfer->value)
            ->call('settle')
            ->assertHasNoErrors()
            ->assertSee('LC-00000001');

        $this->assertSame(CommissionStatus::Paid, $sale->commission->refresh()->status);
        $this->assertDatabaseCount('commission_settlements', 1);
    }

    private function pay(
        User $actor,
        Barber $barber,
        Service $service,
        float $price,
        string $paidAt,
    ): Sale {
        $appointment = Appointment::factory()
            ->for(Client::factory())
            ->for($barber)
            ->for($service)
            ->create([
                'price' => $price,
                'status' => AppointmentStatus::PendingPayment,
            ]);
        $sale = app(AppointmentPaymentService::class)->register($appointment, [
            'payment_method' => PaymentMethod::Cash->value,
        ], $actor);
        $sale->update(['paid_at' => $paidAt]);

        return $sale->refresh()->load('commission');
    }
}
