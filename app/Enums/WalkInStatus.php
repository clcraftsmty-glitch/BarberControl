<?php

namespace App\Enums;

enum WalkInStatus: string
{
    case Waiting = 'en_espera';
    case Converted = 'convertido';
    case Left = 'se_retiro';

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'En espera',
            self::Converted => 'Atendido',
            self::Left => 'Se retiró',
        };
    }
}
