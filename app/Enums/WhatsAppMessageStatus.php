<?php

namespace App\Enums;

enum WhatsAppMessageStatus: string
{
    case Pending = 'pendiente';
    case Sent = 'enviado';
    case Delivered = 'entregado';
    case Read = 'leido';
    case Failed = 'fallido';
    case Skipped = 'omitido';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Sent => 'Enviado',
            self::Delivered => 'Entregado',
            self::Read => 'Leído',
            self::Failed => 'Fallido',
            self::Skipped => 'Omitido',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-slate-100 text-slate-700',
            self::Sent => 'bg-blue-100 text-blue-800',
            self::Delivered => 'bg-cyan-100 text-cyan-800',
            self::Read => 'bg-emerald-100 text-emerald-800',
            self::Failed => 'bg-red-100 text-red-800',
            self::Skipped => 'bg-amber-100 text-amber-800',
        };
    }
}
