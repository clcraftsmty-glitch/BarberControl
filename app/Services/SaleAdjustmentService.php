<?php

namespace App\Services;

use App\Enums\CashMovementCategory;
use App\Enums\CashRegisterStatus;
use App\Enums\CommissionAdjustmentStatus;
use App\Enums\CommissionAdjustmentType;
use App\Enums\CommissionStatus;
use App\Enums\SaleStatus;
use App\Enums\UserPermission;
use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use App\Models\CommissionAdjustment;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleAdjustmentService
{
    public function cancel(Sale $sale, string $reason, User $actor): Sale
    {
        return $this->reverse($sale, trim($reason), $actor, SaleStatus::Cancelled);
    }

    public function refund(Sale $sale, string $reason, User $actor): Sale
    {
        return $this->reverse($sale, trim($reason), $actor, SaleStatus::Refunded);
    }

    private function reverse(Sale $sale, string $reason, User $actor, SaleStatus $target): Sale
    {
        if (! $actor->hasPermission(UserPermission::CancelSales)) {
            throw new AuthorizationException('Sólo un administrador puede realizar este ajuste.');
        }

        if ($reason === '') {
            throw ValidationException::withMessages(['adjustment_reason' => 'Escribe el motivo del ajuste.']);
        }

        return DB::transaction(function () use ($sale, $reason, $actor, $target): Sale {
            $sale = Sale::query()->with('commission')->lockForUpdate()->findOrFail($sale->id);

            if ($sale->status !== SaleStatus::Completed) {
                throw ValidationException::withMessages(['adjustment' => 'Esta venta ya fue cancelada o devuelta.']);
            }

            $cashRegister = CashRegisterSession::query()
                ->where('status', CashRegisterStatus::Open->value)
                ->lockForUpdate()
                ->first();

            if (! $cashRegister) {
                throw ValidationException::withMessages(['adjustment' => 'Debes abrir la caja antes de cancelar o devolver una venta.']);
            }

            CashMovement::query()->create([
                'cash_register_session_id' => $cashRegister->id,
                'sale_id' => $sale->id,
                'type' => 'gasto',
                'category' => CashMovementCategory::Refund,
                'amount' => $sale->total,
                'payment_method' => $sale->payment_method,
                'description' => ($target === SaleStatus::Cancelled ? 'Cancelación' : 'Devolución')." de {$sale->folio}: {$reason}",
                'occurred_at' => now(),
                'created_by' => $actor->id,
            ]);

            if ($sale->commission) {
                if ($sale->commission->status === CommissionStatus::Paid) {
                    CommissionAdjustment::query()->create([
                        'barber_id' => $sale->commission->barber_id,
                        'type' => CommissionAdjustmentType::Debit,
                        'amount' => $sale->commission->amount,
                        'reason' => "Recuperación de comisión por {$target->label()} de {$sale->folio}: {$reason}",
                        'status' => CommissionAdjustmentStatus::Pending,
                        'authorized_by' => $actor->id,
                        'authorized_at' => now(),
                        'created_by' => $actor->id,
                    ]);
                } else {
                    $sale->commission->update(['status' => CommissionStatus::Cancelled]);
                }
            }

            $sale->status = $target;
            $sale->refunded_amount = $sale->total;

            if ($target === SaleStatus::Cancelled) {
                $sale->cancelled_at = now();
                $sale->cancelled_by = $actor->id;
                $sale->cancellation_reason = $reason;
            } else {
                $sale->refunded_at = now();
                $sale->refunded_by = $actor->id;
                $sale->refund_reason = $reason;
            }

            $sale->save();

            return $sale->refresh()->load(['commission', 'cashMovements']);
        });
    }
}
