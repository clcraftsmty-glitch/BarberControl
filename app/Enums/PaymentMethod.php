<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'efectivo';
    case Card = 'tarjeta';
    case Transfer = 'transferencia';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Efectivo',
            self::Card => 'Tarjeta',
            self::Transfer => 'Transferencia',
        };
    }
}
