<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Liquidación {{ $settlement->folio }}</title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;background:#e2e8f0;color:#0f172a;font:14px Arial,sans-serif}
        .actions{position:sticky;top:0;display:flex;justify-content:center;gap:8px;padding:12px;background:#0f172a}
        .actions button,.actions a{border:0;border-radius:8px;padding:10px 14px;background:#fff;color:#0f172a;cursor:pointer;font-weight:700;text-decoration:none}
        .sheet{width:216mm;min-height:260mm;margin:20px auto;padding:16mm;background:#fff;box-shadow:0 10px 35px #0f172a26}
        .header{display:flex;justify-content:space-between;border-bottom:3px solid #0f172a;padding-bottom:14px}
        .brand{font-size:24px;font-weight:900}
        .brand-block{display:flex;align-items:center;gap:12px}
        .report-logo{width:96px;height:96px;object-fit:contain}
        .folio{text-align:right}
        .meta{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:18px 0}
        .box{padding:12px;border:1px solid #cbd5e1;border-radius:8px}
        .label{font-size:10px;font-weight:800;text-transform:uppercase;color:#64748b}
        table{width:100%;border-collapse:collapse;margin-top:14px}
        th,td{padding:9px 8px;border-bottom:1px solid #e2e8f0;text-align:left}
        th{font-size:10px;text-transform:uppercase;background:#f8fafc}
        td.num,th.num{text-align:right}
        .services-grid{display:none}
        .service-card{border:1px solid #cbd5e1;border-radius:6px;padding:7px;break-inside:avoid;page-break-inside:avoid}
        .service-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:8px;border-bottom:1px solid #e2e8f0;padding-bottom:4px}
        .service-card-head small{display:block;color:#64748b;margin-top:2px}
        .service-card-commission{white-space:nowrap;font-size:13px}
        .service-card-name{font-weight:700;margin:5px 0}
        .service-card-values{display:grid;grid-template-columns:1fr 1fr;gap:4px;color:#475569;font-size:10px}
        .service-card-values strong{display:block;color:#0f172a;font-size:11px;margin-top:1px}
        .totals{width:340px;margin:18px 0 0 auto}
        .totals div{display:flex;justify-content:space-between;padding:7px}
        .total{border-top:2px solid #0f172a;font-size:18px;font-weight:900}
        .signatures{display:grid;grid-template-columns:1fr 1fr;gap:60px;margin-top:70px;text-align:center;break-inside:avoid;page-break-inside:avoid}
        .line{border-top:1px solid #0f172a;padding-top:7px}
        .notes{margin-top:20px;padding:12px;background:#f8fafc;border-radius:8px;break-inside:avoid;page-break-inside:avoid}
        tr{break-inside:avoid;page-break-inside:avoid}
        @page{size:Letter portrait;margin:8mm}
        @media print{
            body{background:#fff;font-size:12px}
            .actions{display:none}
            .sheet{margin:0;padding:0;box-shadow:none;width:auto;min-height:auto}
            .header{padding-bottom:8px}
            .brand{font-size:21px}
            .report-logo{width:82px;height:82px}
            .meta{gap:8px;margin:10px 0}
            .box{padding:7px}
            h3{margin:9px 0 4px}
            table{margin-top:4px}
            th,td{padding:5px 6px;line-height:1.15}
            .services-table{display:none}
            .services-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px 8px;margin-top:5px}
            .totals{margin-top:8px}
            .totals div{padding:4px 7px}
            .total{font-size:16px}
            .notes{margin-top:9px;padding:7px}
            .signatures{margin-top:32px}
        }
    </style>
</head>
<body>
<div class="actions"><button onclick="window.print()">Imprimir comprobante</button><a href="{{ route('commissions.receipt.pdf', $settlement) }}">Descargar PDF</a><button onclick="window.close()">Cerrar</button></div>
<main class="sheet">
    <header class="header"><div class="brand-block">@if($settings->logoUrl())<img src="{{ $settings->logoUrl() }}" alt="{{ $settings->business_name }}" class="report-logo">@endif<div><div class="brand">{{ mb_strtoupper($settings->business_name) }}</div><div>Comprobante de liquidación de comisiones</div></div></div><div class="folio"><div class="label">Folio</div><strong>{{ $settlement->folio }}</strong><div>{{ $settlement->paid_at->format('d/m/Y H:i') }}</div></div></header>
    <section class="meta"><div class="box"><div class="label">Barbero</div><strong>{{ $settlement->barber->display_name }}</strong></div><div class="box"><div class="label">Periodo {{ $settlement->period_type->label() }}</div><strong>{{ $settlement->period_start->format('d/m/Y') }} – {{ $settlement->period_end->format('d/m/Y') }}</strong></div><div class="box"><div class="label">Forma de pago</div><strong>{{ $settlement->payment_method->label() }}</strong>@if($settlement->payment_reference)<div>Ref. {{ $settlement->payment_reference }}</div>@endif</div><div class="box"><div class="label">Autorizó y pagó</div><strong>{{ $settlement->creator?->name ?? 'Usuario eliminado' }}</strong></div></section>
    <h3>Servicios liquidados</h3>
    <table class="services-table"><thead><tr><th>Venta / fecha</th><th>Servicio</th><th class="num">Base</th><th class="num">%</th><th class="num">Comisión</th></tr></thead><tbody>@foreach($settlement->commissions as $commission)<tr><td>{{ $commission->sale->folio }}<br><small>{{ $commission->sale->paid_at->format('d/m/Y H:i') }}</small></td><td>{{ $commission->sale->service_name_snapshot }}</td><td class="num">{{ $settings->formatMoney($commission->base_amount) }}</td><td class="num">{{ number_format((float)$commission->percentage,2) }}%</td><td class="num"><strong>{{ $settings->formatMoney($commission->amount) }}</strong></td></tr>@endforeach</tbody></table>
    <section class="services-grid" aria-label="Servicios liquidados en dos columnas">
        @foreach($settlement->commissions as $commission)
            <article class="service-card">
                <div class="service-card-head">
                    <div><strong>{{ $commission->sale->folio }}</strong><small>{{ $commission->sale->paid_at->format('d/m/Y H:i') }}</small></div>
                    <strong class="service-card-commission">{{ $settings->formatMoney($commission->amount) }}</strong>
                </div>
                <div class="service-card-name">{{ $commission->sale->service_name_snapshot }}</div>
                <div class="service-card-values">
                    <span>Base<strong>{{ $settings->formatMoney($commission->base_amount) }}</strong></span>
                    <span>Comisión<strong>{{ number_format((float)$commission->percentage,2) }}%</strong></span>
                </div>
            </article>
        @endforeach
    </section>
    @if($settlement->adjustments->isNotEmpty())<h3>Ajustes autorizados</h3><table><thead><tr><th>Tipo</th><th>Motivo</th><th>Autorizó</th><th class="num">Importe</th></tr></thead><tbody>@foreach($settlement->adjustments as $adjustment)<tr><td>{{ $adjustment->type->label() }}</td><td>{{ $adjustment->reason }}</td><td>{{ $adjustment->authorizer?->name ?? 'Usuario eliminado' }}</td><td class="num">{{ $adjustment->signedAmount() >= 0 ? '+' : '−' }}{{ $settings->formatMoney(abs($adjustment->signedAmount())) }}</td></tr>@endforeach</tbody></table>@endif
    <div class="totals"><div><span>Comisiones</span><strong>{{ $settings->formatMoney($settlement->commissions_total) }}</strong></div><div><span>Ajustes</span><strong>{{ $settings->formatMoney($settlement->adjustments_total) }}</strong></div><div class="total"><span>Total pagado</span><strong>{{ $settings->formatMoney($settlement->total_paid) }}</strong></div></div>
    @if($settlement->notes)<div class="notes"><div class="label">Notas</div>{{ $settlement->notes }}</div>@endif
    <div class="signatures"><div class="line">Firma del barbero</div><div class="line">Firma de autorización</div></div>
</main>
</body></html>
