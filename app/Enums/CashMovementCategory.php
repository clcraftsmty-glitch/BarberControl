<?php

namespace App\Enums;

enum CashMovementCategory: string
{
    case ServiceSale = 'venta_servicio';
    case ManualIncome = 'ingreso_manual';
    case Supplies = 'insumos';
    case OperatingExpense = 'gasto_operativo';
    case Withdrawal = 'retiro';
    case Refund = 'devolucion';
    case CommissionPayment = 'pago_comision';
    case CashAdjustmentIncome = 'ajuste_entrada';
    case CashAdjustmentExpense = 'ajuste_salida';
    case Other = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::ServiceSale => 'Venta de servicio',
            self::ManualIncome => 'Ingreso manual',
            self::Supplies => 'Compra de insumos',
            self::OperatingExpense => 'Gasto operativo',
            self::Withdrawal => 'Retiro de efectivo',
            self::Refund => 'Cancelación o devolución',
            self::CommissionPayment => 'Pago de comisiones',
            self::CashAdjustmentIncome => 'Ajuste de entrada',
            self::CashAdjustmentExpense => 'Ajuste de salida',
            self::Other => 'Otro',
        };
    }

    public function type(): string
    {
        return match ($this) {
            self::ServiceSale, self::ManualIncome, self::CashAdjustmentIncome => 'ingreso',
            self::Supplies, self::OperatingExpense, self::Withdrawal, self::Refund, self::CommissionPayment, self::CashAdjustmentExpense => 'gasto',
            self::Other => 'ambos',
        };
    }

    public function isAllowedFor(string $type): bool
    {
        return $this->type() === 'ambos' || $this->type() === $type;
    }

    public function requiresAdministrator(): bool
    {
        return in_array($this, [self::CashAdjustmentIncome, self::CashAdjustmentExpense], true);
    }

    /** @return list<self> */
    public static function manualFor(string $type, bool $includeAdjustments = true): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $category): bool => $category !== self::ServiceSale
                && $category !== self::Refund
                && $category !== self::CommissionPayment
                && $category->isAllowedFor($type)
                && ($includeAdjustments || ! $category->requiresAdministrator()),
        ));
    }
}
