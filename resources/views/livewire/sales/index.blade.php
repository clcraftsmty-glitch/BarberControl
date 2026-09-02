<div>
    <x-slot name="header">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-600">Control financiero</p>
            <h1 class="text-xl font-bold text-slate-900">Tickets e historial de ventas</h1>
        </div>
    </x-slot>

    @if (session('status'))
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('status') }}</div>
    @endif

    @error('adjustment')
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ $message }}</div>
    @enderror

    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(260px,1.4fr)_170px_170px_170px_180px_auto]">
            <label class="block">
                <span class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">Buscar</span>
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Folio, cliente o teléfono" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
            </label>
            <label class="block">
                <span class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">Desde</span>
                <input wire:model.live="dateFrom" type="date" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
            </label>
            <label class="block">
                <span class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">Hasta</span>
                <input wire:model.live="dateTo" type="date" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
            </label>
            <label class="block">
                <span class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">Estado</span>
                <select wire:model.live="statusFilter" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">Todos</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">Método</span>
                <select wire:model.live="paymentFilter" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">Todos</option>
                    @foreach ($paymentMethods as $method)
                        <option value="{{ $method->value }}">{{ $method->label() }}</option>
                    @endforeach
                </select>
            </label>
            <div class="flex items-end">
                <button wire:click="clearFilters" type="button" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 hover:bg-slate-50">Limpiar</button>
            </div>
        </div>
    </section>

    <section class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="font-black text-slate-950">Ventas registradas</h2>
            <p class="mt-1 text-sm text-slate-500">{{ $sales->total() }} resultado{{ $sales->total() === 1 ? '' : 's' }}</p>
        </div>

        @if ($sales->isEmpty())
            <div class="px-6 py-14 text-center">
                <p class="font-bold text-slate-700">No se encontraron ventas.</p>
                <p class="mt-1 text-sm text-slate-500">Modifica los filtros para ampliar la búsqueda.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50 text-left text-[11px] font-extrabold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Folio y fecha</th>
                            <th class="px-5 py-3">Cliente</th>
                            <th class="px-5 py-3">Servicio y barbero</th>
                            <th class="px-5 py-3">Pago</th>
                            <th class="px-5 py-3 text-right">Total</th>
                            <th class="px-5 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($sales as $sale)
                            <tr wire:key="sale-{{ $sale->id }}" class="align-top hover:bg-slate-50/70">
                                <td class="whitespace-nowrap px-5 py-4">
                                    <a href="{{ route('sales.show', $sale) }}" wire:navigate class="font-black text-brand-700 hover:underline">{{ $sale->folio }}</a>
                                    <p class="mt-1 text-xs font-medium text-slate-500">{{ $sale->paid_at->format('d/m/Y H:i') }}</p>
                                    <span class="mt-2 inline-flex rounded-full px-2.5 py-1 text-[10px] font-extrabold uppercase {{ $sale->status->badgeClasses() }}">{{ $sale->status->label() }}</span>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="font-bold text-slate-900">{{ $sale->client_name_snapshot }}</p>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">{{ $sale->client_phone_snapshot }}</p>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="font-bold text-slate-900">{{ $sale->service_name_snapshot }}</p>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">{{ $sale->barber_name_snapshot }} · {{ $sale->service_duration_minutes_snapshot }} min</p>
                                </td>
                                <td class="px-5 py-4 text-sm font-semibold text-slate-700">
                                    {{ $sale->payment_method->label() }}
                                    @if ($sale->payment_reference)
                                        <p class="mt-1 text-xs text-slate-500">{{ $sale->payment_reference }}</p>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-right text-base font-black text-slate-950">${{ number_format((float) $sale->total, 2) }}</td>
                                <td class="px-5 py-4">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('sales.show', $sale) }}" wire:navigate class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Ver</a>
                                        <a href="{{ route('sales.ticket.print', $sale) }}" target="_blank" class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-bold text-white hover:bg-slate-800">{{ $sale->ticketLogs->where('action', 'impresion')->isNotEmpty() ? 'Reimprimir' : 'Imprimir' }}</a>
                                        @can('cancel', $sale)
                                            <details class="relative" x-data @click.outside="$el.removeAttribute('open')">
                                                <summary class="list-none cursor-pointer rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Más</summary>
                                                <div class="absolute right-0 z-20 mt-2 w-44 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-xl">
                                                    <button wire:click="openAdjustment({{ $sale->id }}, 'cancel')" type="button" class="block w-full px-3 py-2.5 text-left text-xs font-bold text-red-700 hover:bg-red-50">Cancelar venta</button>
                                                    <button wire:click="openAdjustment({{ $sale->id }}, 'refund')" type="button" class="block w-full px-3 py-2.5 text-left text-xs font-bold text-amber-700 hover:bg-amber-50">Registrar devolución</button>
                                                </div>
                                            </details>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-5 py-4">{{ $sales->links() }}</div>
        @endif
    </section>

    @if ($showAdjustmentModal)
        <div class="fixed inset-0 z-[70] overflow-y-auto px-4 py-6" role="dialog" aria-modal="true">
            <button wire:click="closeAdjustment" type="button" class="fixed inset-0 bg-slate-950/60" aria-label="Cerrar"></button>
            <div class="relative mx-auto w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
                <form wire:submit="applyAdjustment">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <p class="text-xs font-bold uppercase tracking-wide {{ $adjustmentType === 'refund' ? 'text-amber-600' : 'text-red-600' }}">Acción administrativa</p>
                        <h3 class="mt-1 text-xl font-black text-slate-950">{{ $adjustmentType === 'refund' ? 'Registrar devolución' : 'Cancelar venta' }}</h3>
                        <p class="mt-1 text-sm text-slate-500">Se generará un movimiento de salida y se cancelará la comisión.</p>
                    </div>
                    <div class="p-5">
                        <label for="adjustment-reason" class="block text-sm font-bold text-slate-700">Motivo obligatorio</label>
                        <textarea id="adjustment-reason" wire:model="adjustment_reason" rows="4" class="mt-2 w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" placeholder="Describe por qué se realiza este ajuste"></textarea>
                        <x-input-error :messages="$errors->get('adjustment_reason')" class="mt-2" />
                        <x-input-error :messages="$errors->get('adjustment')" class="mt-2" />
                    </div>
                    <div class="flex justify-end gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4">
                        <button wire:click="closeAdjustment" type="button" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700">Cerrar</button>
                        <button type="submit" class="rounded-xl px-4 py-2.5 text-sm font-bold text-white {{ $adjustmentType === 'refund' ? 'bg-amber-600 hover:bg-amber-700' : 'bg-red-600 hover:bg-red-700' }}">Confirmar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
