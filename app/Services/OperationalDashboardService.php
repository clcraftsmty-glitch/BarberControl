<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\CashRegisterStatus;
use App\Enums\SaleStatus;
use App\Enums\UserRole;
use App\Enums\WalkInStatus;
use App\Models\Appointment;
use App\Models\BusinessSetting;
use App\Models\CashRegisterSession;
use App\Models\Sale;
use App\Models\User;
use App\Models\WalkInEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class OperationalDashboardService
{
    /** @return array<string, mixed> */
    public function metricsFor(User $user): array
    {
        $toleranceMinutes = BusinessSetting::current()->arrival_tolerance_minutes;
        $barberId = $user->role === UserRole::Barber
            ? $user->barberProfile?->id
            : null;
        $appointments = Appointment::query()
            ->with([
                'client:id,first_name,last_name,phone',
                'barber:id,display_name',
                'service:id,name,duration_minutes',
            ])
            ->whereDate('starts_at', today()->toDateString())
            ->when(
                $user->role === UserRole::Barber,
                fn (Builder $query) => $barberId
                    ? $query->where('barber_id', $barberId)
                    : $query->whereRaw('1 = 0'),
            )
            ->orderBy('starts_at')
            ->get();

        $operational = $appointments->reject(fn (Appointment $appointment) => in_array(
            $appointment->status,
            [AppointmentStatus::Cancelled, AppointmentStatus::NoShow, AppointmentStatus::Rescheduled],
            true,
        ));
        $active = $operational->reject(fn (Appointment $appointment) => $appointment->status === AppointmentStatus::Completed);
        $waitingWalkIns = WalkInEntry::query()
            ->where('status', WalkInStatus::Waiting->value)
            ->when($barberId, fn (Builder $query) => $query->where(function (Builder $query) use ($barberId): void {
                $query->whereNull('preferred_barber_id')
                    ->orWhere('preferred_barber_id', $barberId)
                    ->orWhere('assigned_barber_id', $barberId);
            }))
            ->count();
        $waitingAppointments = $operational
            ->where('status', AppointmentStatus::Arrived)
            ->count();
        $lateAppointments = $operational
            ->filter(fn (Appointment $appointment) => in_array(
                $appointment->status,
                [AppointmentStatus::Pending, AppointmentStatus::Confirmed],
                true,
            ) && $appointment->starts_at->addMinutes($toleranceMinutes)->isPast())
            ->map(function (Appointment $appointment) use ($toleranceMinutes): Appointment {
                $lateFrom = $appointment->starts_at->addMinutes($toleranceMinutes);
                $appointment->setAttribute('late_minutes', max(1, (int) $lateFrom->diffInMinutes(now())));

                return $appointment;
            })
            ->sortByDesc('late_minutes')
            ->values();
        $completedWithWait = $operational->filter(
            fn (Appointment $appointment) => $appointment->arrived_at && $appointment->service_started_at,
        );
        $averageWaitSeconds = $completedWithWait->isEmpty()
            ? 0
            : (int) round($completedWithWait->average(fn (Appointment $appointment) => $appointment->waitingDurationSeconds()));
        $barberPerformance = $this->barberPerformance($operational);
        $canSeeFinancials = $user->can('viewAny', Sale::class)
            && $user->can('viewAny', CashRegisterSession::class);
        $cashSession = $canSeeFinancials
            ? CashRegisterSession::query()
                ->with('opener:id,name')
                ->where('status', CashRegisterStatus::Open->value)
                ->first()
            : null;
        $sales = $canSeeFinancials
            ? Sale::query()
                ->where('status', SaleStatus::Completed->value)
                ->whereDate('paid_at', today()->toDateString())
                ->get(['id', 'total'])
            : collect();

        return [
            'appointmentsToday' => $operational->count(),
            'waitingClients' => $waitingAppointments + $waitingWalkIns,
            'waitingAppointments' => $waitingAppointments,
            'waitingWalkIns' => $waitingWalkIns,
            'servicesInProgress' => $operational->where('status', AppointmentStatus::InService)->count(),
            'pendingPayments' => $operational->where('status', AppointmentStatus::PendingPayment)->count(),
            'completedToday' => $operational->where('status', AppointmentStatus::Completed)->count(),
            'averageWaitSeconds' => $averageWaitSeconds,
            'barberPerformance' => $barberPerformance,
            'lateAppointments' => $lateAppointments,
            'activeAppointments' => $active->values(),
            'canSeeFinancials' => $canSeeFinancials,
            'cashSession' => $cashSession,
            'cashIsOpen' => (bool) $cashSession,
            'expectedCash' => $cashSession?->expectedCashNow() ?? 0,
            'salesToday' => (float) $sales->sum('total'),
            'salesCount' => $sales->count(),
            'refreshedAt' => now(),
        ];
    }

    /** @param Collection<int, Appointment> $appointments
     * @return Collection<int, array{barber_id: int, name: string, services: int, average_seconds: int}>
     */
    private function barberPerformance(Collection $appointments): Collection
    {
        return $appointments
            ->filter(fn (Appointment $appointment) => $appointment->service_started_at && $appointment->service_finished_at)
            ->groupBy('barber_id')
            ->map(function (Collection $appointments): array {
                /** @var Appointment $first */
                $first = $appointments->first();

                return [
                    'barber_id' => $first->barber_id,
                    'name' => $first->barber->display_name,
                    'services' => $appointments->count(),
                    'average_seconds' => (int) round($appointments->average(
                        fn (Appointment $appointment) => $appointment->serviceDurationSeconds(),
                    )),
                ];
            })
            ->sortBy('name')
            ->values();
    }
}
