<div>
    <x-slot name="header">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-600">Historial del cliente</p>
            <h1 class="truncate text-lg font-bold text-slate-900">{{ $client->full_name }}</h1>
        </div>
    </x-slot>

    <div class="mx-auto max-w-[1500px] space-y-5">
        <section class="flex flex-col justify-between gap-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center">
            <div class="flex min-w-0 items-center gap-4">
                <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-brand-100 text-xl font-black text-brand-700">{{ str($client->first_name)->substr(0, 1)->upper() }}{{ str($client->last_name)->substr(0, 1)->upper() }}</span>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="truncate text-2xl font-black tracking-tight text-slate-950">{{ $client->full_name }}</h2>
                        <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $client->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' }}">{{ $client->is_active ? 'Activo' : 'Inactivo' }}</span>
                    </div>
                    <p class="mt-1 text-sm text-slate-500">Cliente #{{ $client->id }} · Desde {{ $client->created_at->format('d/m/Y') }} · {{ $client->phone }}</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('clients.index') }}" wire:navigate class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 18-6-6 6-6" /></svg>Clientes</a>
                @can('update', $client)
                    <a href="{{ route('clients.edit', $client) }}" wire:navigate class="inline-flex items-center justify-center rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-800">Editar ficha</a>
                @endcan
                @if ($client->is_active)
                    @can('deactivate', $client)
                        <button type="button" wire:click="deactivate" wire:confirm="¿Desactivar a {{ $client->full_name }}?" class="inline-flex items-center justify-center rounded-xl border border-red-200 bg-red-50 px-4 py-2.5 text-sm font-bold text-red-700 hover:bg-red-100">Desactivar</button>
                    @endcan
                @endif
            </div>
        </section>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('status') }}</div>
        @endif

        <section class="grid gap-3 sm:grid-cols-2 {{ $canSeeFinancials ? 'xl:grid-cols-6' : 'xl:grid-cols-5' }}">
            <article class="rounded-2xl border border-blue-200 bg-blue-50 p-4">
                <p class="text-xs font-bold uppercase tracking-wide text-blue-700">Visitas completadas</p>
                <p class="mt-2 text-3xl font-black text-blue-950">{{ $completedVisitCount }}</p>
                <p class="mt-1 text-xs text-blue-700">Servicios atendidos</p>
            </article>
            <article class="rounded-2xl border border-cyan-200 bg-cyan-50 p-4">
                <p class="text-xs font-bold uppercase tracking-wide text-cyan-700">Última visita</p>
                <p class="mt-2 text-xl font-black text-cyan-950">{{ $daysSinceLastVisit === null ? 'Sin visitas' : ($daysSinceLastVisit === 0 ? 'Hoy' : 'Hace '.$daysSinceLastVisit.' días') }}</p>
                <p class="mt-1 text-xs text-cyan-700">{{ $lastVisit?->format('d/m/Y') ?? 'Todavía no atendido' }}</p>
            </article>
            <article class="rounded-2xl border border-violet-200 bg-violet-50 p-4">
                <p class="text-xs font-bold uppercase tracking-wide text-violet-700">Promedio de visitas</p>
                <p class="mt-2 text-xl font-black text-violet-950">{{ $averageVisitIntervalDays ? 'Cada '.$averageVisitIntervalDays.' días' : 'Sin promedio' }}</p>
                <p class="mt-1 text-xs text-violet-700">Intervalo entre visitas</p>
            </article>
            <article class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                <p class="text-xs font-bold uppercase tracking-wide text-emerald-700">Barbero habitual</p>
                <p class="mt-2 truncate text-xl font-black text-emerald-950">{{ $usualBarber['name'] ?? 'Sin definir' }}</p>
                <p class="mt-1 text-xs text-emerald-700">{{ $usualBarber ? $usualBarber['visits'].' visita'.($usualBarber['visits'] === 1 ? '' : 's') : 'No hay historial suficiente' }}</p>
            </article>
            <article class="rounded-2xl border border-red-200 bg-red-50 p-4">
                <p class="text-xs font-bold uppercase tracking-wide text-red-700">Incidencias</p>
                <p class="mt-2 text-xl font-black text-red-950">{{ $cancelledCount + $noShowCount }}</p>
                <p class="mt-1 text-xs text-red-700">{{ $cancelledCount }} canceladas · {{ $noShowCount }} ausencias</p>
            </article>
            @if ($canSeeFinancials)
                <article class="rounded-2xl border border-orange-200 bg-orange-50 p-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-orange-700">Total gastado</p>
                    <p class="mt-2 text-2xl font-black text-orange-950">${{ number_format($totalSpent, 2) }}</p>
                    <p class="mt-1 text-xs text-orange-700">{{ $paymentCount }} pago{{ $paymentCount === 1 ? '' : 's' }} completado{{ $paymentCount === 1 ? '' : 's' }}</p>
                </article>
            @endif
        </section>

        <section class="grid gap-5 xl:grid-cols-[minmax(0,1.25fr)_minmax(330px,.75fr)]">
            <article class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-200 text-amber-800"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v3.75m9-1.5a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM12 16.5h.008v.008H12V16.5Z" /></svg></span>
                    <div><h3 class="font-black text-amber-950">Notas importantes y preferencias</h3><p class="mt-2 whitespace-pre-line text-sm leading-6 text-amber-900">{{ $client->notes ?: 'No hay notas ni preferencias registradas para este cliente.' }}</p></div>
                </div>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="font-black text-slate-950">Contacto y preferencias</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">Teléfono</dt><dd class="text-right font-bold text-slate-900"><a href="tel:{{ $client->phone }}" class="hover:text-brand-700">{{ $client->phone }}</a></dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">Correo</dt><dd class="truncate text-right font-bold text-slate-900">{{ $client->email ?: 'Sin información' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">Nacimiento</dt><dd class="text-right font-bold text-slate-900">{{ $client->birth_date?->format('d/m/Y') ?: 'Sin información' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">Preferencia registrada</dt><dd class="text-right font-bold text-slate-900">{{ $client->preferredBarber?->name ?: 'Sin preferencia' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">WhatsApp</dt><dd><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $client->whatsapp_opt_in ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">{{ $client->whatsapp_opt_in ? 'Autorizado' : 'Sin autorización' }}</span></dd></div>
                </dl>
            </article>
        </section>

        <section class="grid gap-5 xl:grid-cols-[minmax(0,.75fr)_minmax(0,1.25fr)]">
            <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4"><h3 class="font-black text-slate-950">Servicios frecuentes</h3><p class="mt-0.5 text-xs text-slate-500">Basado en citas terminadas.</p></div>
                @forelse ($frequentServices as $service)
                    <div class="border-b border-slate-100 px-5 py-4 last:border-b-0">
                        <div class="flex items-center justify-between gap-3"><p class="font-bold text-slate-900">{{ $service['name'] }}</p><span class="text-sm font-black text-slate-950">{{ $service['visits'] }}</span></div>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-brand-500" style="width: {{ $service['percentage'] }}%"></div></div>
                        <p class="mt-1 text-xs text-slate-500">{{ $service['percentage'] }}% de sus visitas</p>
                    </div>
                @empty
                    <div class="px-6 py-12 text-center"><p class="font-bold text-slate-700">Sin servicios terminados.</p><p class="mt-1 text-sm text-slate-500">Las preferencias aparecerán después de sus visitas.</p></div>
                @endforelse
            </article>

            <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4"><div><h3 class="font-black text-slate-950">Últimas citas</h3><p class="mt-0.5 text-xs text-slate-500">Actividad reciente, estados y servicios.</p></div><a href="{{ route('appointments.calendar') }}" wire:navigate class="text-xs font-bold text-brand-700">Ver calendario</a></div>
                @if ($latestAppointments->isEmpty())
                    <div class="px-6 py-12 text-center"><p class="font-bold text-slate-700">Este cliente todavía no tiene citas.</p></div>
                @else
                    <div class="divide-y divide-slate-100">
                        @foreach ($latestAppointments as $appointment)
                            <div class="grid gap-3 px-5 py-4 sm:grid-cols-[100px_1fr_1fr_auto] sm:items-center">
                                <div><p class="font-black text-slate-950">{{ $appointment->starts_at->format('d/m/Y') }}</p><p class="text-xs text-slate-500">{{ $appointment->starts_at->format('H:i') }}</p></div>
                                <div><p class="font-bold text-slate-900">{{ $appointment->service->name }}</p><p class="text-xs text-slate-500">{{ $appointment->service->duration_minutes }} min</p></div>
                                <div><p class="text-sm font-bold text-slate-800">{{ $appointment->barber->display_name }}</p><p class="text-xs text-slate-500">${{ number_format((float) $appointment->price, 2) }}</p></div>
                                <span class="w-fit rounded-full px-2.5 py-1 text-[10px] font-extrabold uppercase {{ $appointment->status->badgeClasses() }}">{{ $appointment->status->label() }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </article>
        </section>

        @if ($canSeeFinancials)
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"><div><h3 class="font-black text-slate-950">Tickets y pagos</h3><p class="mt-0.5 text-xs text-slate-500">Historial de cobros, cancelaciones, devoluciones y tickets.</p></div><a href="{{ route('sales.index', ['q' => $client->phone]) }}" wire:navigate class="text-xs font-bold text-brand-700">Abrir historial de ventas</a></div>
                @if ($sales->isEmpty())
                    <div class="px-6 py-12 text-center"><p class="font-bold text-slate-700">No hay pagos registrados.</p><p class="mt-1 text-sm text-slate-500">Los tickets aparecerán después del primer cobro.</p></div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50 text-left text-[11px] font-extrabold uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Folio y fecha</th><th class="px-5 py-3">Servicio</th><th class="px-5 py-3">Pago</th><th class="px-5 py-3">Estado</th><th class="px-5 py-3">Tickets</th><th class="px-5 py-3 text-right">Acciones</th></tr></thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($sales as $sale)
                                    <tr class="hover:bg-slate-50/70">
                                        <td class="px-5 py-4"><a href="{{ route('sales.show', $sale) }}" wire:navigate class="font-black text-brand-700">{{ $sale->folio }}</a><p class="mt-1 text-xs text-slate-500">{{ $sale->paid_at->format('d/m/Y H:i') }}</p></td>
                                        <td class="px-5 py-4"><p class="text-sm font-bold text-slate-900">{{ $sale->service_name_snapshot }}</p><p class="mt-1 text-xs text-slate-500">{{ $sale->barber_name_snapshot }}</p></td>
                                        <td class="px-5 py-4"><p class="font-black text-slate-950">${{ number_format((float) $sale->total, 2) }}</p><p class="mt-1 text-xs text-slate-500">{{ $sale->payment_method->label() }}</p></td>
                                        <td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-[10px] font-extrabold uppercase {{ $sale->status->badgeClasses() }}">{{ $sale->status->label() }}</span></td>
                                        <td class="px-5 py-4 text-sm text-slate-600">{{ $sale->ticket_logs_count }} salida{{ $sale->ticket_logs_count === 1 ? '' : 's' }}</td>
                                        <td class="px-5 py-4"><div class="flex justify-end gap-2"><a href="{{ route('sales.ticket.pdf', $sale) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">PDF</a><a href="{{ route('sales.ticket.print', $sale) }}" target="_blank" class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-bold text-white hover:bg-slate-800">Imprimir</a></div></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @endif
    </div>
</div>
