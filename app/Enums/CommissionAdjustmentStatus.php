<?php

namespace App\Enums;

enum CommissionAdjustmentStatus: string
{
    case Pending = 'pendiente';
    case Applied = 'aplicado';
    case Cancelled = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Applied => 'Aplicado',
            self::Cancelled => 'Cancelado',
        };
    }
}
