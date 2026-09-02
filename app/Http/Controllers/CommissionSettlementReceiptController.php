<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use App\Models\CommissionSettlement;
use App\Services\CommissionSettlementPdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CommissionSettlementReceiptController extends Controller
{
    public function print(CommissionSettlement $commissionSettlement): View
    {
        Gate::authorize('view', $commissionSettlement);

        return view('commissions.receipt', [
            'settlement' => $this->load($commissionSettlement),
            'settings' => BusinessSetting::current(),
        ]);
    }

    public function pdf(CommissionSettlement $commissionSettlement, CommissionSettlementPdf $pdf): Response
    {
        Gate::authorize('view', $commissionSettlement);
        $commissionSettlement = $this->load($commissionSettlement);

        return response($pdf->render($commissionSettlement), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="liquidacion-'.$commissionSettlement->folio.'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function load(CommissionSettlement $settlement): CommissionSettlement
    {
        return $settlement->load(['barber', 'creator', 'commissions.sale', 'adjustments.authorizer']);
    }
}
