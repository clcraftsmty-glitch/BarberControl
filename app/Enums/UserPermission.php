<?php

namespace App\Enums;

enum UserPermission: string
{
    case ViewFinancialInformation = 'finanzas.consultar';
    case CancelSales = 'ventas.cancelar';
    case AdjustCash = 'caja.ajustar';
    case SettleCommissions = 'comisiones.liquidar';

    public function label(): string
    {
        return match ($this) {
            self::ViewFinancialInformation => 'Consultar información financiera',
            self::CancelSales => 'Cancelar ventas y devoluciones',
            self::AdjustCash => 'Registrar y autorizar ajustes de caja',
            self::SettleCommissions => 'Liquidar y ajustar comisiones',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ViewFinancialInformation => 'Permite consultar ventas, caja, cortes, métricas y comisiones.',
            self::CancelSales => 'Permite cancelar ventas y registrar devoluciones con motivo.',
            self::AdjustCash => 'Permite registrar ingresos, gastos, ajustes y autorizar diferencias.',
            self::SettleCommissions => 'Permite crear ajustes y marcar comisiones como pagadas.',
        };
    }

    /** @return list<string> */
    public static function defaultsFor(UserRole $role): array
    {
        return match ($role) {
            UserRole::Administrator => array_column(self::cases(), 'value'),
            UserRole::Receptionist => [self::ViewFinancialInformation->value],
            UserRole::Barber => [],
        };
    }
}
