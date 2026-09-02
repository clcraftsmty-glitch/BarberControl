<?php

namespace App\Enums;

enum WhatsAppMessageType: string
{
    case Confirmation = 'confirmacion';
    case Reminder24Hours = 'recordatorio_24h';
    case Reminder2Hours = 'recordatorio_2h';
    case Cancellation = 'cancelacion';
    case Rescheduled = 'reprogramacion';
    case Ticket = 'ticket';

    public function label(): string
    {
        return match ($this) {
            self::Confirmation => 'Confirmación de cita',
            self::Reminder24Hours => 'Recordatorio 24 horas',
            self::Reminder2Hours => 'Recordatorio 2 horas',
            self::Cancellation => 'Cancelación de cita',
            self::Rescheduled => 'Reprogramación de cita',
            self::Ticket => 'Ticket de venta',
        };
    }
}
