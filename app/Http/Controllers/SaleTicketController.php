<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use App\Models\Sale;
use App\Models\SaleTicketLog;
use App\Services\ThermalTicketPdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SaleTicketController extends Controller
{
    public function print(Sale $sale): View
    {
        Gate::authorize('print', $sale);
        $sale->load(['creator']);
        $this->log($sale, 'impresion');

        return view('sales.ticket', [
            'sale' => $sale,
            'settings' => BusinessSetting::current(),
        ]);
    }

    public function pdf(Sale $sale, ThermalTicketPdf $pdf): Response
    {
        Gate::authorize('print', $sale);
        $sale->load(['creator']);
        $this->log($sale, 'pdf');

        return response($pdf->render($sale), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="ticket-'.$sale->folio.'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function whatsapp(Sale $sale, ThermalTicketPdf $pdf): Response
    {
        return response($pdf->render($sale->load('creator')), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="ticket-'.$sale->folio.'.pdf"',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    private function log(Sale $sale, string $action): void
    {
        SaleTicketLog::query()->create([
            'sale_id' => $sale->id,
            'action' => $action,
            'created_by' => auth()->id(),
        ]);
    }
}
