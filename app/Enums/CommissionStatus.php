<?php

namespace App\Enums;

enum CommissionStatus: string
{
    case Pending = 'pendiente';
    case Paid = 'pagada';
    case Cancelled = 'cancelada';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Paid => 'Pagada',
            self::Cancelled => 'Cancelada',
        };
    }
}
