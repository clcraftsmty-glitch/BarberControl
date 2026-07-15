<?php

namespace App\Enums;

enum UserRole: string
{
    case Administrator = 'administrador';
    case Receptionist = 'recepcionista';
    case Barber = 'barbero';

    public function label(): string
    {
        return match ($this) {
            self::Administrator => 'Administrador',
            self::Receptionist => 'Recepcionista',
            self::Barber => 'Barbero',
        };
    }
}
