<?php

namespace Tests\Feature\CashRegister;

use App\Enums\CashMovementCategory;
use App\Enums\CashRegisterStatus;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\CashRegisterService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CashRegisterReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_receptionist_cannot_authorize_a_cash_difference(): void
    {
        $receptionist = User::factory()->create(['role' => UserRole::Receptionist]);
        $session = app(CashRegisterService::class)->open(500, null, $receptionist);

        try {
            app(CashRegisterService::class)->close($session, 490, null, $receptionist, 'Faltan diez pesos');
            $this->fail('El cierre con diferencia debió requerir autorización administrativa.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'La diferencia debe ser revisada y autorizada por un administrador.',
                $exception->errors()['actual_cash'][0],
            );
        }

        $this->assertDatabaseHas('cash_register_sessions', [
            'id' => $session->id,
            'status' => CashRegisterStatus::Open->value,
        ]);
    }

    public function test_administrator_must_explain_and_authorizes_the_difference(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $session = app(CashRegisterService::class)->open(300, null, $administrator);

        try {
            app(CashRegisterService::class)->close($session, 325, null, $administrator);
            $this->fail('El cierre con diferencia debió requerir motivo.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('difference_reason', $exception->errors());
        }

        $closed = app(CashRegisterService::class)->close(
            $session,
            325,
            'Cierre supervisado',
            $administrator,
            'Sobrante localizado durante el conteo',
        );

        $this->assertSame('25.00', $closed->difference);
        $this->assertSame('Sobrante localizado durante el conteo', $closed->difference_reason);
        $this->assertSame($administrator->id, $closed->difference_authorized_by);
        $this->assertNotNull($closed->difference_authorized_at);
    }

    public function test_movements_are_classified_and_require_the_cash_adjustment_permission(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $authorizedReceptionist = User::factory()->create([
            'role' => UserRole::Receptionist,
            'permissions' => [
                UserPermission::ViewFinancialInformation->value,
                UserPermission::AdjustCash->value,
            ],
        ]);
        $restrictedReceptionist = User::factory()->create([
            'role' => UserRole::Receptionist,
            'permissions' => [UserPermission::ViewFinancialInformation->value],
        ]);
        $service = app(CashRegisterService::class);
        $session = $service->open(100, null, $administrator);

        $expense = $service->recordMovement(
            $session,
            'gasto',
            35,
            'Navajas y material',
            $authorizedReceptionist,
            CashMovementCategory::Supplies->value,
        );
        $adjustment = $service->recordMovement(
            $session,
            'ingreso',
            10,
            'Ajuste aprobado',
            $administrator,
            CashMovementCategory::CashAdjustmentIncome->value,
        );

        $this->assertSame(CashMovementCategory::Supplies, $expense->category);
        $this->assertSame(CashMovementCategory::CashAdjustmentIncome, $adjustment->category);

        $this->expectException(AuthorizationException::class);
        $service->recordMovement(
            $session,
            'gasto',
            5,
            'Ajuste no autorizado',
            $restrictedReceptionist,
            CashMovementCategory::CashAdjustmentExpense->value,
        );
    }

    public function test_daily_cut_export_contains_reconciliation_methods_categories_and_movements(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $service = app(CashRegisterService::class);
        $session = $service->open(200, 'Inicio del día', $administrator);
        $service->recordMovement(
            $session,
            'gasto',
            20,
            'Compra de insumos',
            $administrator,
            CashMovementCategory::Supplies->value,
        );
        $service->close($session, 180, null, $administrator);

        $response = $this->actingAs($administrator)->get(route('cash-register.export', $session));

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringContainsString('BARBERCONTROL', $content);
        $this->assertStringContainsString('CORTE Y CONCILIACIÓN DE CAJA', $content);
        $this->assertStringContainsString('DESGLOSE POR MÉTODO', $content);
        $this->assertStringContainsString('Efectivo', $content);
        $this->assertStringContainsString('Tarjeta', $content);
        $this->assertStringContainsString('Transferencia', $content);
        $this->assertStringContainsString('Compra de insumos', $content);
    }

    public function test_barber_cannot_export_a_cash_cut(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $barber = User::factory()->create(['role' => UserRole::Barber]);
        $session = app(CashRegisterService::class)->open(0, null, $administrator);

        $this->actingAs($barber)
            ->get(route('cash-register.export', $session))
            ->assertForbidden();
    }

    public function test_history_screen_shows_open_and_closed_sessions(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $service = app(CashRegisterService::class);
        $closed = $service->open(50, null, $administrator);
        $service->close($closed, 50, null, $administrator);
        $open = $service->open(75, null, $administrator);

        $this->actingAs($administrator)
            ->get(route('cash-register.index'))
            ->assertOk()
            ->assertSee('Historial de aperturas y cierres')
            ->assertSee((string) $closed->id)
            ->assertSee((string) $open->id);
    }
}
