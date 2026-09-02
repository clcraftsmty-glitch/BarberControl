<?php

namespace App\Enums;

enum SaleStatus: string
{
    case Completed = 'completada';
    case Cancelled = 'cancelada';
    case Refunded = 'devuelta';

    public function label(): string
    {
        return match ($this) {
            self::Completed => 'Completada',
            self::Cancelled => 'Cancelada',
            self::Refunded => 'Devuelta',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Completed => 'bg-emerald-100 text-emerald-800',
            self::Cancelled => 'bg-red-100 text-red-800',
            self::Refunded => 'bg-amber-100 text-amber-800',
        };
    }
}
