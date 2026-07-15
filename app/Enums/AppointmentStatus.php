<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Pending = 'pendiente';
    case Confirmed = 'confirmada';
    case InProgress = 'en_proceso';
    case Completed = 'terminada';
    case Cancelled = 'cancelada';
    case NoShow = 'no_asistio';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Confirmed => 'Confirmada',
            self::InProgress => 'En proceso',
            self::Completed => 'Terminada',
            self::Cancelled => 'Cancelada',
            self::NoShow => 'No asistió',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-100 text-amber-800',
            self::Confirmed => 'bg-blue-100 text-blue-800',
            self::InProgress => 'bg-violet-100 text-violet-800',
            self::Completed => 'bg-emerald-100 text-emerald-800',
            self::Cancelled => 'bg-slate-200 text-slate-700',
            self::NoShow => 'bg-red-100 text-red-800',
        };
    }
}
