<?php

namespace App\Enums;

enum CommissionAdjustmentType: string
{
    case Credit = 'bono';
    case Debit = 'descuento';

    public function label(): string
    {
        return match ($this) {
            self::Credit => 'Bono',
            self::Debit => 'Descuento',
        };
    }

    public function signedAmount(float $amount): float
    {
        return $this === self::Credit ? $amount : -$amount;
    }
}
