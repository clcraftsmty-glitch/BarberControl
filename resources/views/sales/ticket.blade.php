<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ticket {{ $sale->folio }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #e5e7eb; color: #111827; font-family: "Courier New", monospace; }
        .actions { position: sticky; top: 0; display: flex; justify-content: center; gap: 8px; padding: 12px; background: #0f172a; }
        .actions button, .actions a { border: 0; border-radius: 8px; padding: 10px 14px; background: #fff; color: #0f172a; cursor: pointer; font: 700 14px Arial, sans-serif; text-decoration: none; }
        .ticket { width: 80mm; min-height: 120mm; margin: 20px auto; padding: 5mm 4mm 8mm; background: #fff; box-shadow: 0 8px 30px rgba(15,23,42,.18); font-size: 11px; line-height: 1.45; }
        .center { text-align: center; }
        .brand { margin: 0; font-size: 19px; font-weight: 900; }
        .logo { display: block; max-width: 56mm; max-height: 30mm; margin: 0 auto 7px; object-fit: contain; }
        .subtitle { margin: 2px 0 10px; font-size: 9px; letter-spacing: .14em; }
        .business-data { margin: 2px 0; font-size: 9px; white-space: pre-line; }
        .divider { margin: 9px 0; border-top: 1px dashed #111827; }
        .folio { margin: 0; font-size: 14px; }
        .row { display: flex; justify-content: space-between; gap: 8px; margin-top: 4px; }
        .row span:first-child { color: #475569; }
        .service { margin: 0; font-size: 13px; font-weight: 900; }
        .total { align-items: baseline; margin-top: 8px; font-size: 13px; font-weight: 900; }
        .total strong { font-size: 19px; }
        .status { margin-top: 8px; border: 1px solid #111827; padding: 4px 6px; text-align: center; font-weight: 900; }
        .thanks { margin: 12px 0 0; font-weight: 900; }
        @page { size: 80mm auto; margin: 0; }
        @media print {
            body { background: #fff; }
            .actions { display: none !important; }
            .ticket { width: 80mm; min-height: 0; margin: 0; padding: 5mm 4mm 8mm; box-shadow: none; }
        }
    </style>
</head>
<body>
    @php($tax = $settings->includedTaxBreakdown($sale->total))
    <div class="actions">
        <button type="button" onclick="window.print()">Imprimir ticket</button>
        <a href="{{ route('sales.ticket.pdf', $sale) }}">Descargar PDF</a>
        <button type="button" onclick="window.close()">Cerrar</button>
    </div>
    <main class="ticket">
        <header class="center">
            @if($settings->logoUrl())<img src="{{ $settings->logoUrl() }}" alt="{{ $settings->business_name }}" class="logo">@endif
            <h1 class="brand">{{ mb_strtoupper($settings->business_name) }}</h1>
            @if($settings->ticket_header)<p class="subtitle">{{ mb_strtoupper($settings->ticket_header) }}</p>@endif
            @if($settings->legal_name)<p class="business-data"><strong>{{ $settings->legal_name }}</strong></p>@endif
            @if($settings->tax_id)<p class="business-data">RFC: {{ $settings->tax_id }}</p>@endif
            @if($settings->address)<p class="business-data">{{ $settings->address }}</p>@endif
            @if($settings->phones)<p class="business-data">Tel: {{ implode(' / ', $settings->phones) }}</p>@endif
            <div class="divider"></div>
            <h2 class="folio">TICKET {{ $sale->folio }}</h2>
        </header>
        <div class="divider"></div>
        <div class="row"><span>Fecha</span><strong>{{ $sale->paid_at->format('d/m/Y H:i') }}</strong></div>
        <div class="row"><span>Cliente</span><strong>{{ $sale->client_name_snapshot }}</strong></div>
        <div class="row"><span>Teléfono</span><strong>{{ $sale->client_phone_snapshot }}</strong></div>
        <div class="row"><span>Barbero</span><strong>{{ $sale->barber_name_snapshot }}</strong></div>
        <div class="divider"></div>
        <p class="service">{{ $sale->service_name_snapshot }}</p>
        <div class="row"><span>Duración</span><strong>{{ $sale->service_duration_minutes_snapshot }} min</strong></div>
        <div class="row"><span>Precio</span><strong>{{ $settings->formatMoney($sale->unit_price_snapshot) }}</strong></div>
        <div class="divider"></div>
        @if($tax['tax'] > 0)
            <div class="row"><span>Subtotal</span><strong>{{ $settings->formatMoney($tax['subtotal']) }}</strong></div>
            <div class="row"><span>{{ $settings->tax_name }} {{ $settings->tax_rate }}% incluido</span><strong>{{ $settings->formatMoney($tax['tax']) }}</strong></div>
        @endif
        <div class="row total"><span>TOTAL</span><strong>{{ $settings->formatMoney($sale->total) }}</strong></div>
        <div class="row"><span>Pago</span><strong>{{ $sale->payment_method->label() }}</strong></div>
        @if ($sale->payment_reference)<div class="row"><span>Referencia</span><strong>{{ $sale->payment_reference }}</strong></div>@endif
        <div class="row"><span>Atendió</span><strong>{{ $sale->creator?->name ?? 'Sistema' }}</strong></div>
        <div class="status">{{ strtoupper($sale->status->label()) }}</div>
        <p class="center thanks" style="white-space: pre-line">{{ $settings->ticket_footer ?: 'Gracias por tu visita' }}</p>
        <p class="center">Conserva este comprobante</p>
</main>
@if (request()->boolean('autoprint'))
    <script>window.addEventListener('load', () => window.print());</script>
@endif
</body>
</html>
