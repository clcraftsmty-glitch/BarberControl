<?php

namespace App\Enums;

enum CashRegisterStatus: string
{
    case Open = 'abierta';
    case Closed = 'cerrada';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Abierta',
            self::Closed => 'Cerrada',
        };
    }
}
