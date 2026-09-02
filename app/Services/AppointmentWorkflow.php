<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\BusinessSetting;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentWorkflow
{
    public function __construct(
        private WhatsAppNotificationService $whatsApp,
        private BarberOccupancyService $occupancy,
    ) {}

    public function transition(
        Appointment $appointment,
        AppointmentStatus $target,
        User $actor,
        bool $force = false,
    ): Appointment {
        $appointment = DB::transaction(function () use ($appointment, $target, $actor, $force): Appointment {
            $appointment = Appointment::query()
                ->with('sale')
                ->lockForUpdate()
                ->findOrFail($appointment->id);

            $this->assertActorCanTransition($appointment, $target, $actor, $force);
            $this->assertTransitionIsValid($appointment, $target, $force);
            $this->assertCancellationRule($appointment, $target, $actor);

            if ($target === AppointmentStatus::InService) {
                $this->occupancy->assertCanStart($appointment->barber_id, $appointment->id);
            }

            $this->applyOperationalTimestamps($appointment, $target, $force);
            $appointment->status = $target;
            $appointment->updated_by = $actor->id;
            $appointment->save();

            return $appointment->refresh();
        });

        if ($target === AppointmentStatus::Cancelled) {
            $this->whatsApp->cancellation($appointment, $actor);
        } elseif ($target === AppointmentStatus::Rescheduled) {
            $this->whatsApp->rescheduled($appointment, $actor);
        }

        return $appointment;
    }

    private function applyOperationalTimestamps(
        Appointment $appointment,
        AppointmentStatus $target,
        bool $force,
    ): void {
        $current = $appointment->status;

        if ($current === $target) {
            return;
        }

        if ($force && in_array($target, [AppointmentStatus::Pending, AppointmentStatus::Confirmed], true)) {
            $appointment->arrived_at = null;
            $appointment->service_started_at = null;
            $appointment->service_finished_at = null;

            return;
        }

        if ($target === AppointmentStatus::Arrived) {
            $appointment->arrived_at = now();
            $appointment->service_started_at = null;
            $appointment->service_finished_at = null;

            return;
        }

        if ($target === AppointmentStatus::InService) {
            $appointment->arrived_at ??= now();
            $appointment->service_started_at = now();
            $appointment->service_finished_at = null;

            return;
        }

        if ($target === AppointmentStatus::PendingPayment) {
            $appointment->arrived_at ??= now();
            $appointment->service_started_at ??= now();
            $appointment->service_finished_at = now();

            return;
        }

        if (
            $current === AppointmentStatus::InService
            && in_array($target, [
                AppointmentStatus::Cancelled,
                AppointmentStatus::NoShow,
                AppointmentStatus::Rescheduled,
            ], true)
        ) {
            $appointment->service_finished_at ??= now();
        }
    }

    private function assertActorCanTransition(
        Appointment $appointment,
        AppointmentStatus $target,
        User $actor,
        bool $force,
    ): void {
        if ($force && $actor->role !== UserRole::Administrator) {
            throw new AuthorizationException('Solo un administrador puede forzar el estado de una cita.');
        }

        if ($actor->role === UserRole::Administrator) {
            return;
        }

        $exceptionalTargets = [
            AppointmentStatus::Cancelled,
            AppointmentStatus::NoShow,
            AppointmentStatus::Rescheduled,
        ];

        if ($actor->role === UserRole::Receptionist) {
            return;
        }

        $ownsAppointment = $actor->role === UserRole::Barber
            && $appointment->barber()->where('user_id', $actor->id)->exists();

        if (! $ownsAppointment || in_array($target, $exceptionalTargets, true)) {
            throw new AuthorizationException('No tienes permiso para realizar esta transición.');
        }
    }

    private function assertTransitionIsValid(
        Appointment $appointment,
        AppointmentStatus $target,
        bool $force,
    ): void {
        $current = $appointment->status;

        if ($current === $target) {
            return;
        }

        if ($target === AppointmentStatus::Completed && ! $appointment->sale) {
            $this->fail('La cita solo puede terminarse después de registrar el pago.');
        }

        if ($force) {
            return;
        }

        $exceptionalTargets = [
            AppointmentStatus::Cancelled,
            AppointmentStatus::NoShow,
            AppointmentStatus::Rescheduled,
        ];

        $isExceptional = ! $current->isFinal() && in_array($target, $exceptionalTargets, true);
        $isOperational = $current->nextOperationalStatus() === $target;

        if (! $isExceptional && ! $isOperational) {
            $this->fail("No se puede cambiar de {$current->label()} a {$target->label()}.");
        }
    }

    private function assertCancellationRule(
        Appointment $appointment,
        AppointmentStatus $target,
        User $actor,
    ): void {
        if ($target !== AppointmentStatus::Cancelled || $actor->role === UserRole::Administrator) {
            return;
        }

        $noticeHours = BusinessSetting::current()->cancellation_notice_hours;

        if ($noticeHours > 0 && $appointment->starts_at->lessThan(now()->addHours($noticeHours))) {
            $this->fail("Las cancelaciones con menos de {$noticeHours} horas de anticipación requieren autorización de un administrador.");
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['status' => $message]);
    }
}
