<?php

namespace App\Services;

use App\Enums\CashMovementCategory;
use App\Enums\CashRegisterStatus;
use App\Enums\CommissionAdjustmentStatus;
use App\Enums\CommissionAdjustmentType;
use App\Enums\CommissionPeriod;
use App\Enums\CommissionStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserPermission;
use App\Models\Barber;
use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use App\Models\Commission;
use App\Models\CommissionAdjustment;
use App\Models\CommissionSettlement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommissionSettlementService
{
    public function createAdjustment(
        Barber $barber,
        CommissionAdjustmentType $type,
        float $amount,
        string $reason,
        User $actor,
    ): CommissionAdjustment {
        $this->assertAdministrator($actor);

        if ($amount <= 0) {
            $this->fail('El importe del ajuste debe ser mayor que cero.', 'adjustment_amount');
        }

        if (mb_strlen(trim($reason)) < 5) {
            $this->fail('Escribe un motivo de al menos 5 caracteres.', 'adjustment_reason');
        }

        return CommissionAdjustment::query()->create([
            'barber_id' => $barber->id,
            'type' => $type,
            'amount' => round($amount, 2),
            'reason' => trim($reason),
            'status' => CommissionAdjustmentStatus::Pending,
            'authorized_by' => $actor->id,
            'authorized_at' => now(),
            'created_by' => $actor->id,
        ]);
    }

    public function settle(
        Barber $barber,
        CommissionPeriod $period,
        CarbonImmutable $periodStart,
        CarbonImmutable $periodEnd,
        PaymentMethod $paymentMethod,
        ?string $paymentReference,
        ?string $notes,
        User $actor,
    ): CommissionSettlement {
        $this->assertAdministrator($actor);

        if ($periodEnd->lessThan($periodStart)) {
            $this->fail('La fecha final no puede ser anterior a la inicial.', 'period_end');
        }

        return DB::transaction(function () use ($barber, $period, $periodStart, $periodEnd, $paymentMethod, $paymentReference, $notes, $actor): CommissionSettlement {
            Barber::query()->lockForUpdate()->findOrFail($barber->id);

            $cashRegister = CashRegisterSession::query()
                ->where('status', CashRegisterStatus::Open->value)
                ->lockForUpdate()
                ->first();

            if (! $cashRegister) {
                $this->fail('Debes abrir la caja antes de registrar una liquidaciÃ³n.', 'settlement');
            }

            $commissions = Commission::query()
                ->where('barber_id', $barber->id)
                ->where('status', CommissionStatus::Pending->value)
                ->whereHas('sale', fn ($query) => $query
                    ->whereDate('paid_at', '>=', $periodStart->toDateString())
                    ->whereDate('paid_at', '<=', $periodEnd->toDateString()))
                ->lockForUpdate()
                ->get();
            $adjustments = CommissionAdjustment::query()
                ->where('barber_id', $barber->id)
                ->where('status', CommissionAdjustmentStatus::Pending->value)
                ->lockForUpdate()
                ->get();

            if ($commissions->isEmpty() && $adjustments->isEmpty()) {
                $this->fail('No hay comisiones ni ajustes pendientes para este periodo.', 'settlement');
            }

            $commissionsTotal = round((float) $commissions->sum('amount'), 2);
            $adjustmentsTotal = round((float) $adjustments->sum(fn (CommissionAdjustment $adjustment) => $adjustment->signedAmount()), 2);
            $totalPaid = round($commissionsTotal + $adjustmentsTotal, 2);

            if ($totalPaid <= 0) {
                $this->fail('Los descuentos no pueden dejar una liquidación en cero o negativa.', 'settlement');
            }

            $folioNumber = $this->nextFolioNumber();
            $paidAt = now();
            $settlement = CommissionSettlement::query()->create([
                'folio_number' => $folioNumber,
                'folio' => sprintf('LC-%08d', $folioNumber),
                'barber_id' => $barber->id,
                'period_type' => $period,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'commissions_total' => $commissionsTotal,
                'adjustments_total' => $adjustmentsTotal,
                'total_paid' => $totalPaid,
                'payment_method' => $paymentMethod,
                'payment_reference' => filled($paymentReference) ? trim($paymentReference) : null,
                'notes' => filled($notes) ? trim($notes) : null,
                'paid_at' => $paidAt,
                'created_by' => $actor->id,
            ]);

            Commission::query()->whereKey($commissions->modelKeys())->update([
                'commission_settlement_id' => $settlement->id,
                'status' => CommissionStatus::Paid->value,
                'paid_at' => $paidAt,
                'paid_by' => $actor->id,
                'updated_at' => $paidAt,
            ]);
            CommissionAdjustment::query()->whereKey($adjustments->modelKeys())->update([
                'commission_settlement_id' => $settlement->id,
                'status' => CommissionAdjustmentStatus::Applied->value,
                'updated_at' => $paidAt,
            ]);

            CashMovement::query()->create([
                'cash_register_session_id' => $cashRegister->id,
                'sale_id' => null,
                'commission_settlement_id' => $settlement->id,
                'type' => 'gasto',
                'category' => CashMovementCategory::CommissionPayment,
                'amount' => $totalPaid,
                'payment_method' => $paymentMethod,
                'description' => "Pago de comisiones {$settlement->folio} a {$barber->display_name}",
                'occurred_at' => $paidAt,
                'created_by' => $actor->id,
            ]);

            return $settlement->load(['barber', 'commissions.sale', 'adjustments.authorizer', 'creator']);
        });
    }

    private function assertAdministrator(User $actor): void
    {
        if (! $actor->hasPermission(UserPermission::SettleCommissions)) {
            throw new AuthorizationException('Sólo un administrador puede autorizar liquidaciones y ajustes.');
        }
    }

    private function nextFolioNumber(): int
    {
        $sequence = DB::table('document_sequences')->where('name', 'commission_settlements')->lockForUpdate()->first();

        if (! $sequence) {
            DB::table('document_sequences')->insert([
                'name' => 'commission_settlements',
                'current_value' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $sequence = DB::table('document_sequences')->where('name', 'commission_settlements')->lockForUpdate()->first();
        }

        $next = ((int) $sequence->current_value) + 1;
        DB::table('document_sequences')->where('name', 'commission_settlements')->update([
            'current_value' => $next,
            'updated_at' => now(),
        ]);

        return $next;
    }

    private function fail(string $message, string $field): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
