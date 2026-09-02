<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\CashMovementCategory;
use App\Enums\CashRegisterStatus;
use App\Enums\CommissionStatus;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use App\Models\Commission;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentPaymentService
{
    public function __construct(private WhatsAppNotificationService $whatsApp) {}

    /** @param array{payment_method: string, payment_reference?: string|null} $data */
    public function register(Appointment $appointment, array $data, User $actor): Sale
    {
        if (! $actor->hasRole(UserRole::Administrator, UserRole::Receptionist)) {
            throw new AuthorizationException('No tienes permiso para registrar cobros.');
        }

        $sale = DB::transaction(function () use ($appointment, $data, $actor): Sale {
            $cashRegister = CashRegisterSession::query()
                ->where('status', CashRegisterStatus::Open->value)
                ->lockForUpdate()
                ->first();

            if (! $cashRegister) {
                $this->fail('Debes abrir la caja antes de registrar un cobro.');
            }

            $appointment = Appointment::query()
                ->with(['client', 'service', 'barber', 'sale'])
                ->lockForUpdate()
                ->findOrFail($appointment->id);

            if ($appointment->status !== AppointmentStatus::PendingPayment) {
                $this->fail('La cita debe estar pendiente de cobro.');
            }

            if ($appointment->sale) {
                $this->fail('Esta cita ya tiene un pago registrado.');
            }

            $method = PaymentMethod::tryFrom($data['payment_method'] ?? '');

            if (! $method) {
                $this->fail('Selecciona un método de pago válido.', 'payment_method');
            }

            $paidAt = now();
            $total = round((float) $appointment->price, 2);
            $servicePercentage = (float) $appointment->service->commission_percentage;
            $percentage = $servicePercentage > 0
                ? $servicePercentage
                : (float) $appointment->barber->default_commission_percentage;
            $commissionAmount = round($total * ($percentage / 100), 2);
            $folioNumber = $this->nextFolioNumber();

            $sale = Sale::query()->create([
                'folio_number' => $folioNumber,
                'folio' => sprintf('V-%08d', $folioNumber),
                'status' => SaleStatus::Completed,
                'appointment_id' => $appointment->id,
                'client_id' => $appointment->client_id,
                'barber_id' => $appointment->barber_id,
                'service_id' => $appointment->service_id,
                'subtotal' => $total,
                'total' => $total,
                'payment_method' => $method,
                'payment_reference' => filled($data['payment_reference'] ?? null)
                    ? trim($data['payment_reference'])
                    : null,
                'paid_at' => $paidAt,
                'created_by' => $actor->id,
                'client_name_snapshot' => $appointment->client->full_name,
                'client_phone_snapshot' => $appointment->client->phone,
                'barber_name_snapshot' => $appointment->barber->display_name,
                'service_name_snapshot' => $appointment->service->name,
                'service_description_snapshot' => $appointment->service->description,
                'service_duration_minutes_snapshot' => $appointment->service->duration_minutes,
                'unit_price_snapshot' => $total,
                'commission_percentage_snapshot' => $percentage,
            ]);

            CashMovement::query()->create([
                'cash_register_session_id' => $cashRegister->id,
                'sale_id' => $sale->id,
                'type' => 'ingreso',
                'category' => CashMovementCategory::ServiceSale,
                'amount' => $total,
                'payment_method' => $method,
                'description' => "Cobro de cita #{$appointment->id}",
                'occurred_at' => $paidAt,
                'created_by' => $actor->id,
            ]);

            Commission::query()->create([
                'sale_id' => $sale->id,
                'barber_id' => $appointment->barber_id,
                'base_amount' => $total,
                'percentage' => $percentage,
                'amount' => $commissionAmount,
                'status' => CommissionStatus::Pending,
            ]);

            if ($appointment->service_started_at) {
                $appointment->service_finished_at ??= $paidAt;
            }

            $appointment->status = AppointmentStatus::Completed;
            $appointment->updated_by = $actor->id;
            $appointment->save();

            return $sale->load(['cashMovement', 'commission']);
        });

        $this->whatsApp->ticket($sale, $actor);

        return $sale;
    }

    private function fail(string $message, string $field = 'payment'): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    private function nextFolioNumber(): int
    {
        $sequence = DB::table('document_sequences')
            ->where('name', 'sales')
            ->lockForUpdate()
            ->first();

        if (! $sequence) {
            DB::table('document_sequences')->insert([
                'name' => 'sales',
                'current_value' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $sequence = DB::table('document_sequences')
                ->where('name', 'sales')
                ->lockForUpdate()
                ->first();
        }

        $next = ((int) $sequence->current_value) + 1;

        DB::table('document_sequences')->where('name', 'sales')->update([
            'current_value' => $next,
            'updated_at' => now(),
        ]);

        return $next;
    }
}
