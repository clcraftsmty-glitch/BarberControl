<div>
    <x-slot name="header">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-600">Detalle de venta</p>
            <h1 class="text-xl font-bold text-slate-900">{{ $sale->folio }}</h1>
        </div>
    </x-slot>

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('sales.index') }}" wire:navigate class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 18-6-6 6-6" /></svg>
            Historial
        </a>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('sales.ticket.pdf', $sale) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M7.5 10.5 12 15m0 0 4.5-4.5M12 15V3" /></svg>
                Descargar PDF
            </a>
            <a href="{{ route('sales.ticket.print', $sale) }}" target="_blank" class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-800">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6.75 7.5V3.75h10.5V7.5m-10.5 9v3.75h10.5V16.5m-12 0h13.5A2.25 2.25 0 0 0 21 14.25v-4.5A2.25 2.25 0 0 0 18.75 7.5H5.25A2.25 2.25 0 0 0 3 9.75v4.5a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                {{ $sale->ticketLogs->where('action', 'impresion')->isNotEmpty() ? 'Reimprimir ticket' : 'Imprimir ticket' }}
            </a>
        </div>
    </div>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="space-y-5">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-100 pb-5">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Folio de venta</p>
                        <p class="mt-1 text-2xl font-black text-slate-950">{{ $sale->folio }}</p>
                        <p class="mt-1 text-sm font-medium text-slate-500">{{ $sale->paid_at->translatedFormat('d \d\e F \d\e Y, H:i') }}</p>
                    </div>
                    <span class="rounded-full px-3 py-1.5 text-xs font-extrabold uppercase {{ $sale->status->badgeClasses() }}">{{ $sale->status->label() }}</span>
                </div>

                <dl class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Cliente</dt><dd class="mt-1 font-bold text-slate-950">{{ $sale->client_name_snapshot }}</dd><dd class="mt-1 text-sm text-slate-500">{{ $sale->client_phone_snapshot }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Barbero</dt><dd class="mt-1 font-bold text-slate-950">{{ $sale->barber_name_snapshot }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Servicio</dt><dd class="mt-1 font-bold text-slate-950">{{ $sale->service_name_snapshot }}</dd><dd class="mt-1 text-sm text-slate-500">{{ $sale->service_duration_minutes_snapshot }} minutos</dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Registró</dt><dd class="mt-1 font-bold text-slate-950">{{ $sale->creator?->name ?? 'Sistema' }}</dd></div>
                </dl>

                @if ($sale->service_description_snapshot)
                    <div class="mt-5 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600">{{ $sale->service_description_snapshot }}</div>
                @endif
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="font-black text-slate-950">Movimientos y auditoría</h2>
                <div class="mt-4 divide-y divide-slate-100">
                    @foreach ($sale->cashMovements->sortBy('occurred_at') as $movement)
                        <div class="flex items-center justify-between gap-4 py-3 text-sm">
                            <div><p class="font-bold text-slate-900">{{ $movement->description }}</p><p class="mt-1 text-xs text-slate-500">{{ $movement->occurred_at->format('d/m/Y H:i') }} · {{ $movement->creator?->name ?? 'Sistema' }}</p></div>
                            <strong class="{{ $movement->type === 'gasto' ? 'text-red-700' : 'text-emerald-700' }}">{{ $movement->type === 'gasto' ? '-' : '+' }}${{ number_format((float) $movement->amount, 2) }}</strong>
                        </div>
                    @endforeach
                </div>
                @if ($sale->status === \App\Enums\SaleStatus::Cancelled)
                    <div class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"><strong>Motivo de cancelación:</strong> {{ $sale->cancellation_reason }}<p class="mt-1 text-xs">{{ $sale->cancelled_at?->format('d/m/Y H:i') }} · {{ $sale->canceller?->name }}</p></div>
                @elseif ($sale->status === \App\Enums\SaleStatus::Refunded)
                    <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800"><strong>Motivo de devolución:</strong> {{ $sale->refund_reason }}<p class="mt-1 text-xs">{{ $sale->refunded_at?->format('d/m/Y H:i') }} · {{ $sale->refunder?->name }}</p></div>
                @endif
            </section>
        </div>

        <aside class="space-y-5">
            <section class="rounded-2xl bg-slate-950 p-5 text-white shadow-lg">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Resumen del cobro</p>
                <div class="mt-5 space-y-3 text-sm">
                    <div class="flex justify-between gap-3"><span class="text-slate-400">Precio</span><strong>${{ number_format((float) $sale->unit_price_snapshot, 2) }}</strong></div>
                    <div class="flex justify-between gap-3"><span class="text-slate-400">Método</span><strong>{{ $sale->payment_method->label() }}</strong></div>
                    @if ($sale->payment_reference)<div class="flex justify-between gap-3"><span class="text-slate-400">Referencia</span><strong class="text-right">{{ $sale->payment_reference }}</strong></div>@endif
                </div>
                <div class="mt-5 flex items-end justify-between border-t border-white/10 pt-5"><span class="font-bold text-slate-300">Total</span><strong class="text-3xl font-black">${{ number_format((float) $sale->total, 2) }}</strong></div>
                @if ($sale->refunded_amount > 0)<div class="mt-3 flex justify-between rounded-xl bg-red-500/15 px-3 py-2 text-sm text-red-200"><span>Reintegrado</span><strong>${{ number_format((float) $sale->refunded_amount, 2) }}</strong></div>@endif
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-black text-slate-950">Historial del ticket</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $sale->ticketLogs->count() }} salida{{ $sale->ticketLogs->count() === 1 ? '' : 's' }} registrada{{ $sale->ticketLogs->count() === 1 ? '' : 's' }}</p>
                <div class="mt-3 space-y-2">
                    @forelse ($sale->ticketLogs->sortByDesc('created_at')->take(8) as $log)
                        <div class="rounded-xl bg-slate-50 px-3 py-2 text-xs"><strong class="capitalize text-slate-700">{{ $log->action }}</strong><p class="mt-1 text-slate-500">{{ $log->created_at->format('d/m/Y H:i') }} · {{ $log->creator?->name ?? 'Sistema' }}</p></div>
                    @empty
                        <p class="text-sm text-slate-500">Todavía no se ha impreso ni descargado.</p>
                    @endforelse
                </div>
            </section>
        </aside>
    </div>
</div>
