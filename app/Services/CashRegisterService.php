<?php

namespace App\Services;

use App\Enums\CashMovementCategory;
use App\Enums\CashRegisterStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashRegisterService
{
    public function open(float $openingAmount, ?string $notes, User $actor): CashRegisterSession
    {
        $this->assertCanOperate($actor);

        if ($openingAmount < 0) {
            $this->fail('El fondo inicial no puede ser negativo.', 'opening_amount');
        }

        return DB::transaction(function () use ($openingAmount, $notes, $actor): CashRegisterSession {
            if (CashRegisterSession::query()->where('status', CashRegisterStatus::Open->value)->lockForUpdate()->exists()) {
                $this->fail('Ya existe una caja abierta. Ciérrala antes de iniciar otra.');
            }

            return CashRegisterSession::query()->create([
                'opened_by' => $actor->id,
                'opened_at' => now(),
                'opening_amount' => round($openingAmount, 2),
                'opening_notes' => filled($notes) ? trim($notes) : null,
                'status' => CashRegisterStatus::Open,
                'open_guard' => 'open',
            ]);
        });
    }

    public function recordMovement(
        CashRegisterSession $session,
        string $type,
        float $amount,
        string $description,
        User $actor,
        ?string $category = null,
    ): CashMovement {
        $this->assertCanAdjust($actor);

        if (! in_array($type, ['ingreso', 'gasto'], true)) {
            $this->fail('El tipo de movimiento no es válido.', 'movement_type');
        }

        if ($amount <= 0) {
            $this->fail('El importe debe ser mayor que cero.', 'movement_amount');
        }

        if (blank($description)) {
            $this->fail('Escribe el concepto del movimiento.', 'movement_description');
        }

        $movementCategory = $category
            ? CashMovementCategory::tryFrom($category)
            : ($type === 'ingreso' ? CashMovementCategory::ManualIncome : CashMovementCategory::OperatingExpense);

        if (! $movementCategory || ! $movementCategory->isAllowedFor($type)) {
            $this->fail('La categoría no corresponde al tipo de movimiento.', 'movement_category');
        }

        if (in_array($movementCategory, [CashMovementCategory::ServiceSale, CashMovementCategory::Refund], true)) {
            $this->fail('Esta categoría se asigna automáticamente desde ventas.', 'movement_category');
        }

        if ($movementCategory->requiresAdministrator() && ! $actor->hasPermission(UserPermission::AdjustCash)) {
            $this->fail('Este ajuste requiere autorización de un administrador.', 'movement_category');
        }

        return DB::transaction(function () use ($session, $type, $amount, $description, $actor, $movementCategory): CashMovement {
            $session = CashRegisterSession::query()->lockForUpdate()->findOrFail($session->id);

            if ($session->status !== CashRegisterStatus::Open) {
                $this->fail('La caja ya está cerrada.');
            }

            return CashMovement::query()->create([
                'cash_register_session_id' => $session->id,
                'sale_id' => null,
                'type' => $type,
                'category' => $movementCategory,
                'amount' => round($amount, 2),
                'payment_method' => PaymentMethod::Cash,
                'description' => trim($description),
                'occurred_at' => now(),
                'created_by' => $actor->id,
            ]);
        });
    }

    public function close(
        CashRegisterSession $session,
        float $actualCash,
        ?string $notes,
        User $actor,
        ?string $differenceReason = null,
    ): CashRegisterSession {
        $this->assertCanOperate($actor);

        if ($actualCash < 0) {
            $this->fail('El efectivo real no puede ser negativo.', 'actual_cash');
        }

        return DB::transaction(function () use ($session, $actualCash, $notes, $actor, $differenceReason): CashRegisterSession {
            $session = CashRegisterSession::query()->lockForUpdate()->findOrFail($session->id);

            if ($session->status !== CashRegisterStatus::Open) {
                $this->fail('La caja ya está cerrada.');
            }

            $expectedCash = $session->expectedCashNow();
            $actualCash = round($actualCash, 2);
            $difference = round($actualCash - $expectedCash, 2);
            $hasDifference = abs($difference) >= 0.01;

            if ($hasDifference && ! $actor->hasPermission(UserPermission::AdjustCash)) {
                $this->fail('La diferencia debe ser revisada y autorizada por un administrador.', 'actual_cash');
            }

            if ($hasDifference && blank($differenceReason)) {
                $this->fail('Escribe el motivo de la diferencia para autorizar el ajuste.', 'difference_reason');
            }

            $session->update([
                'closed_by' => $actor->id,
                'closed_at' => now(),
                'expected_cash' => $expectedCash,
                'actual_cash' => $actualCash,
                'difference' => $difference,
                'difference_reason' => $hasDifference ? trim($differenceReason) : null,
                'difference_authorized_by' => $hasDifference ? $actor->id : null,
                'difference_authorized_at' => $hasDifference ? now() : null,
                'closing_notes' => filled($notes) ? trim($notes) : null,
                'status' => CashRegisterStatus::Closed,
                'open_guard' => null,
            ]);

            return $session->refresh();
        });
    }

    private function assertCanOperate(User $actor): void
    {
        if (! $actor->is_active || ! $actor->hasRole(UserRole::Administrator, UserRole::Receptionist)) {
            throw new AuthorizationException('No tienes permiso para operar la caja.');
        }
    }

    private function assertCanAdjust(User $actor): void
    {
        if (! $actor->hasPermission(UserPermission::AdjustCash)) {
            throw new AuthorizationException('No tienes permiso para registrar ajustes de caja.');
        }
    }

    private function fail(string $message, string $field = 'cash_register'): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
