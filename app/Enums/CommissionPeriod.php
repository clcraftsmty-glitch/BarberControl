<?php

namespace App\Enums;

enum CommissionPeriod: string
{
    case Weekly = 'semanal';
    case Biweekly = 'quincenal';

    public function label(): string
    {
        return match ($this) {
            self::Weekly => 'Semanal',
            self::Biweekly => 'Quincenal',
        };
    }
}
