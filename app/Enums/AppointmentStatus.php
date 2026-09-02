<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Pending = 'pendiente';
    case Confirmed = 'confirmada';
    case Arrived = 'llego';
    case InService = 'en_servicio';
    case PendingPayment = 'pendiente_cobro';
    case Completed = 'terminada';
    case Cancelled = 'cancelada';
    case NoShow = 'no_asistio';
    case Rescheduled = 'reprogramada';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Confirmed => 'Confirmada',
            self::Arrived => 'Llegó',
            self::InService => 'En servicio',
            self::PendingPayment => 'Pendiente de cobro',
            self::Completed => 'Terminada',
            self::Cancelled => 'Cancelada',
            self::NoShow => 'No asistió',
            self::Rescheduled => 'Reprogramada',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-100 text-amber-800',
            self::Confirmed => 'bg-blue-100 text-blue-800',
            self::Arrived => 'bg-cyan-100 text-cyan-800',
            self::InService => 'bg-violet-100 text-violet-800',
            self::PendingPayment => 'bg-orange-100 text-orange-800',
            self::Completed => 'bg-emerald-100 text-emerald-800',
            self::Cancelled => 'bg-slate-200 text-slate-700',
            self::NoShow => 'bg-red-100 text-red-800',
            self::Rescheduled => 'bg-fuchsia-100 text-fuchsia-800',
        };
    }

    public function nextOperationalStatus(): ?self
    {
        return match ($this) {
            self::Pending => self::Confirmed,
            self::Confirmed => self::Arrived,
            self::Arrived => self::InService,
            self::InService => self::PendingPayment,
            self::PendingPayment => self::Completed,
            default => null,
        };
    }

    public function actionLabel(): ?string
    {
        return match ($this) {
            self::Pending => 'Confirmar cita',
            self::Confirmed => 'Marcar llegada',
            self::Arrived => 'Iniciar servicio',
            self::InService => 'Finalizar servicio',
            self::PendingPayment => 'Registrar pago',
            default => null,
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled, self::NoShow, self::Rescheduled], true);
    }

    public function blocksSchedule(): bool
    {
        return ! in_array($this, [self::Cancelled, self::NoShow, self::Rescheduled], true);
    }

    public function dailyGroup(): string
    {
        return match ($this) {
            self::Pending, self::Confirmed => 'upcoming',
            self::Arrived => 'waiting',
            self::InService => 'in_service',
            self::PendingPayment => 'pending_payment',
            self::Completed, self::Cancelled, self::NoShow, self::Rescheduled => 'finished',
        };
    }
}
