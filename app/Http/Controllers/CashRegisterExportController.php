<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Models\BusinessSetting;
use App\Models\CashRegisterSession;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashRegisterExportController extends Controller
{
    public function __invoke(CashRegisterSession $cashRegisterSession): StreamedResponse
    {
        Gate::authorize('view', $cashRegisterSession);

        $cashRegisterSession->load([
            'opener:id,name',
            'closer:id,name',
            'differenceAuthorizer:id,name',
            'movements' => fn ($query) => $query->with('creator:id,name')->orderBy('occurred_at'),
        ]);

        $filename = 'corte-caja-'.$cashRegisterSession->opened_at->format('Y-m-d').'-'.$cashRegisterSession->id.'.csv';
        $settings = BusinessSetting::current();

        return response()->streamDownload(function () use ($cashRegisterSession, $settings): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");

            $row = static fn (array $values) => fputcsv($output, $values);
            $money = static fn ($value): string => number_format((float) $value, 2, '.', '');
            $movements = $cashRegisterSession->movements;

            $row([mb_strtoupper($settings->business_name)]);
            $row([$settings->address]);
            $row([implode(' / ', $settings->phones)]);
            $row(['CORTE Y CONCILIACIÓN DE CAJA', 'Moneda', $settings->currency_code]);
            $row(['Folio de apertura', $cashRegisterSession->id]);
            $row(['Estado', $cashRegisterSession->status->label()]);
            $row(['Apertura', $cashRegisterSession->opened_at->format('d/m/Y H:i'), $cashRegisterSession->opener?->name]);
            $row(['Cierre', $cashRegisterSession->closed_at?->format('d/m/Y H:i') ?? 'Caja abierta', $cashRegisterSession->closer?->name]);
            $row([]);
            $row(['CONCILIACIÓN DE EFECTIVO']);
            $row(['Fondo inicial', $money($cashRegisterSession->opening_amount)]);
            $row(['Efectivo esperado', $money($cashRegisterSession->closed_at ? $cashRegisterSession->expected_cash : $cashRegisterSession->expectedCashNow())]);
            $row(['Efectivo real', $cashRegisterSession->actual_cash !== null ? $money($cashRegisterSession->actual_cash) : 'Pendiente']);
            $row(['Diferencia', $cashRegisterSession->difference !== null ? $money($cashRegisterSession->difference) : 'Pendiente']);
            $row(['Motivo de diferencia', $cashRegisterSession->difference_reason]);
            $row(['Autorizada por', $cashRegisterSession->differenceAuthorizer?->name]);
            $row(['Fecha de autorización', $cashRegisterSession->difference_authorized_at?->format('d/m/Y H:i')]);
            $row([]);
            $row(['DESGLOSE POR MÉTODO', 'INGRESOS', 'GASTOS', 'NETO']);

            foreach (PaymentMethod::cases() as $method) {
                $methodMovements = $movements->where('payment_method', $method);
                $income = (float) $methodMovements->where('type', 'ingreso')->sum('amount');
                $expenses = (float) $methodMovements->where('type', 'gasto')->sum('amount');
                $row([$method->label(), $money($income), $money($expenses), $money($income - $expenses)]);
            }

            $row([]);
            $row(['DESGLOSE POR CATEGORÍA', 'TIPO', 'TOTAL']);
            foreach ($movements->groupBy(fn ($movement) => $movement->category->value) as $categoryMovements) {
                $category = $categoryMovements->first()->category;
                $row([$category->label(), $categoryMovements->first()->type, $money($categoryMovements->sum('amount'))]);
            }

            $row([]);
            $row(['DETALLE DE MOVIMIENTOS']);
            $row(['Fecha y hora', 'Tipo', 'Categoría', 'Método', 'Concepto', 'Importe', 'Registró']);
            foreach ($movements as $movement) {
                $row([
                    $movement->occurred_at->format('d/m/Y H:i'),
                    $movement->type,
                    $movement->category->label(),
                    $movement->payment_method->label(),
                    $movement->description,
                    $money($movement->amount),
                    $movement->creator?->name ?? 'Usuario eliminado',
                ]);
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
