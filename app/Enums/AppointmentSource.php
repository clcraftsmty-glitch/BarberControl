<?php

namespace App\Enums;

enum AppointmentSource: string
{
    case Scheduled = 'programada';
    case WalkIn = 'sin_cita';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Programada',
            self::WalkIn => 'Sin cita',
        };
    }
}
