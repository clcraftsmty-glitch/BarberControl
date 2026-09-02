<div>
    <x-slot name="header">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-600">Control financiero</p>
            <h1 class="text-xl font-bold text-slate-900">Liquidación de comisiones</h1>
        </div>
    </x-slot>

    @if (session('status'))
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
            {{ session('status') }}
            @if (session('settlement_id'))
                <a href="{{ route('commissions.receipt', session('settlement_id')) }}" target="_blank" class="ml-2 font-black underline underline-offset-2">Abrir comprobante</a>
            @endif
        </div>
    @endif
    @error('settlement')<div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ $message }}</div>@enderror

    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Periodo de cálculo</p>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <button type="button" wire:click="previousPeriod" class="h-11 w-11 rounded-xl border border-slate-300 bg-white font-black text-slate-700 hover:bg-slate-50" aria-label="Periodo anterior">‹</button>
                    <label><span class="sr-only">Periodicidad</span><select wire:model.live="periodType" class="h-11 rounded-xl border-slate-300 text-sm font-bold focus:border-brand-500 focus:ring-brand-500">@foreach ($periods as $period)<option value="{{ $period->value }}">{{ $period->label() }}</option>@endforeach</select></label>
                    <div class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-black text-white">{{ \Carbon\CarbonImmutable::parse($periodStart)->format('d/m/Y') }} – {{ \Carbon\CarbonImmutable::parse($periodEnd)->format('d/m/Y') }}</div>
                    <button type="button" wire:click="nextPeriod" class="h-11 w-11 rounded-xl border border-slate-300 bg-white font-black text-slate-700 hover:bg-slate-50" aria-label="Periodo siguiente">›</button>
                    <button type="button" wire:click="currentPeriod" class="h-11 rounded-xl border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 hover:bg-slate-50">Actual</button>
                </div>
            </div>
            <div class="text-sm text-slate-500"><strong class="text-slate-900">{{ $barbers->count() }}</strong> barbero{{ $barbers->count() === 1 ? '' : 's' }} con saldo pendiente</div>
        </div>
    </section>

    <section class="mt-5">
        <div class="mb-3"><h2 class="text-lg font-black text-slate-950">Pendientes por barbero</h2><p class="text-sm text-slate-500">Cada servicio conserva su precio, porcentaje y comisión originales.</p></div>
        <div class="grid gap-4 xl:grid-cols-2">
            @forelse ($barbers as $barber)
                @php
                    $commissionTotal = (float) $barber->commissions->sum('amount');
                    $adjustmentTotal = (float) $barber->commissionAdjustments->sum(fn ($adjustment) => $adjustment->signedAmount());
                    $payableTotal = $commissionTotal + $adjustmentTotal;
                @endphp
                <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-start justify-between gap-4 border-b border-slate-100 bg-slate-50/70 px-5 py-4">
                        <div><h3 class="text-lg font-black text-slate-950">{{ $barber->display_name }}</h3><p class="mt-1 text-xs font-semibold text-slate-500">{{ $barber->commissions->count() }} servicio{{ $barber->commissions->count() === 1 ? '' : 's' }} · {{ $barber->commissionAdjustments->count() }} ajuste{{ $barber->commissionAdjustments->count() === 1 ? '' : 's' }}</p></div>
                        <div class="text-right"><p class="text-xs font-bold uppercase text-slate-500">A pagar</p><p class="text-2xl font-black {{ $payableTotal > 0 ? 'text-emerald-700' : 'text-red-700' }}">${{ number_format($payableTotal, 2) }}</p></div>
                    </div>
                    <div class="divide-y divide-slate-100">
                        @foreach ($barber->commissions->take(4) as $commission)
                            <div class="grid grid-cols-[1fr_auto] gap-3 px-5 py-3 text-sm">
                                <div><p class="font-bold text-slate-800">{{ $commission->sale->service_name_snapshot }}</p><p class="text-xs text-slate-500">{{ $commission->sale->folio }} · {{ $commission->sale->paid_at->format('d/m H:i') }} · {{ number_format((float) $commission->percentage, 2) }}% de ${{ number_format((float) $commission->base_amount, 2) }}</p></div>
                                <strong class="text-emerald-700">${{ number_format((float) $commission->amount, 2) }}</strong>
                            </div>
                        @endforeach
                        @if ($barber->commissions->count() > 4)<p class="px-5 py-2 text-xs font-bold text-slate-500">+ {{ $barber->commissions->count() - 4 }} servicios en el desglose</p>@endif
                        @foreach ($barber->commissionAdjustments as $adjustment)
                            <div class="grid grid-cols-[1fr_auto] gap-3 bg-amber-50/60 px-5 py-3 text-sm"><div><p class="font-bold text-slate-800">{{ $adjustment->type->label() }} autorizado</p><p class="text-xs text-slate-500">{{ $adjustment->reason }} · {{ $adjustment->authorizer?->name }}</p></div><strong class="{{ $adjustment->signedAmount() >= 0 ? 'text-emerald-700' : 'text-red-700' }}">{{ $adjustment->signedAmount() >= 0 ? '+' : '−' }}${{ number_format(abs($adjustment->signedAmount()), 2) }}</strong></div>
                        @endforeach
                    </div>
                    @can('create', \App\Models\CommissionSettlement::class)
                        <div class="flex flex-wrap justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4">
                            <button type="button" wire:click="openAdjustment({{ $barber->id }})" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-100">Agregar ajuste</button>
                            <button type="button" wire:click="openSettlement({{ $barber->id }})" @disabled($payableTotal <= 0) class="rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-brand-700 disabled:cursor-not-allowed disabled:bg-slate-300">Liquidar periodo</button>
                        </div>
                    @endcan
                </article>
            @empty
                <div class="col-span-full rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center"><p class="font-black text-slate-800">No hay comisiones pendientes en este periodo.</p><p class="mt-1 text-sm text-slate-500">Prueba con el periodo anterior o espera a que se registren nuevos pagos.</p></div>
            @endforelse
        </div>
    </section>

    <section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4"><h2 class="text-lg font-black text-slate-950">Historial de liquidaciones</h2><p class="mt-1 text-sm text-slate-500">Pagos realizados y comprobantes disponibles.</p></div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Folio / fecha</th><th class="px-5 py-3">Barbero</th><th class="px-5 py-3">Periodo</th><th class="px-5 py-3 text-right">Comisiones</th><th class="px-5 py-3 text-right">Ajustes</th><th class="px-5 py-3 text-right">Pagado</th><th class="px-5 py-3 text-right">Comprobante</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($history as $settlement)
                        <tr><td class="whitespace-nowrap px-5 py-4"><p class="font-black text-brand-700">{{ $settlement->folio }}</p><p class="text-xs text-slate-500">{{ $settlement->paid_at->format('d/m/Y H:i') }}</p></td><td class="px-5 py-4"><p class="font-bold text-slate-900">{{ $settlement->barber->display_name }}</p><p class="text-xs text-slate-500">{{ $settlement->creator?->name ?? 'Usuario eliminado' }}</p></td><td class="whitespace-nowrap px-5 py-4"><p class="font-semibold">{{ $settlement->period_type->label() }}</p><p class="text-xs text-slate-500">{{ $settlement->period_start->format('d/m/Y') }} – {{ $settlement->period_end->format('d/m/Y') }}</p></td><td class="px-5 py-4 text-right font-semibold">${{ number_format((float) $settlement->commissions_total, 2) }}<p class="text-xs font-normal text-slate-500">{{ $settlement->commissions_count }} servicios</p></td><td class="px-5 py-4 text-right font-semibold {{ (float) $settlement->adjustments_total < 0 ? 'text-red-700' : 'text-slate-700' }}">${{ number_format((float) $settlement->adjustments_total, 2) }}</td><td class="px-5 py-4 text-right text-base font-black text-emerald-700">${{ number_format((float) $settlement->total_paid, 2) }}</td><td class="whitespace-nowrap px-5 py-4 text-right"><a href="{{ route('commissions.receipt', $settlement) }}" target="_blank" class="font-bold text-slate-700 hover:text-brand-700">Imprimir</a><a href="{{ route('commissions.receipt.pdf', $settlement) }}" class="ml-3 font-bold text-brand-700">PDF</a></td></tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-10 text-center text-slate-500">Todavía no hay liquidaciones pagadas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($history->hasPages())<div class="border-t border-slate-200 px-5 py-4">{{ $history->links() }}</div>@endif
    </section>

    @if ($showSettlementModal && $selectedBarber)
        @php
            $modalCommissionsTotal = (float) $selectedBarber->commissions->sum('amount');
            $modalAdjustmentsTotal = (float) $selectedBarber->commissionAdjustments->sum(fn ($adjustment) => $adjustment->signedAmount());
            $modalTotal = $modalCommissionsTotal + $modalAdjustmentsTotal;
        @endphp
        <div class="fixed inset-0 z-[75] overflow-y-auto px-4 py-6" role="dialog" aria-modal="true"><button type="button" wire:click="closeSettlement" class="fixed inset-0 bg-slate-950/65" aria-label="Cerrar"></button><div class="relative mx-auto w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl"><form wire:submit="settle">
            <div class="border-b border-slate-200 px-5 py-4 sm:px-6"><p class="text-xs font-black uppercase tracking-[0.16em] text-brand-600">Liquidar comisiones</p><h3 class="mt-1 text-xl font-black text-slate-950">{{ $selectedBarber->display_name }}</h3><p class="mt-1 text-sm text-slate-500">{{ \Carbon\CarbonImmutable::parse($periodStart)->format('d/m/Y') }} – {{ \Carbon\CarbonImmutable::parse($periodEnd)->format('d/m/Y') }}</p></div>
            <div class="max-h-[58vh] space-y-5 overflow-y-auto p-5 sm:p-6">
                <x-input-error :messages="$errors->get('settlement')" />
                <div class="grid gap-3 sm:grid-cols-3"><div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-bold uppercase text-slate-500">Comisiones</p><p class="mt-1 text-xl font-black">${{ number_format($modalCommissionsTotal, 2) }}</p></div><div class="rounded-xl bg-amber-50 p-4"><p class="text-xs font-bold uppercase text-amber-700">Ajustes</p><p class="mt-1 text-xl font-black">${{ number_format($modalAdjustmentsTotal, 2) }}</p></div><div class="rounded-xl bg-slate-950 p-4 text-white"><p class="text-xs font-bold uppercase text-slate-300">Total a pagar</p><p class="mt-1 text-2xl font-black">${{ number_format($modalTotal, 2) }}</p></div></div>
                <div class="overflow-hidden rounded-xl border border-slate-200"><div class="max-h-56 divide-y divide-slate-100 overflow-y-auto">@foreach ($selectedBarber->commissions as $commission)<div class="flex justify-between gap-3 px-4 py-3 text-sm"><div><p class="font-bold">{{ $commission->sale->service_name_snapshot }}</p><p class="text-xs text-slate-500">{{ $commission->sale->folio }} · {{ number_format((float) $commission->percentage, 2) }}%</p></div><strong>${{ number_format((float) $commission->amount, 2) }}</strong></div>@endforeach @foreach ($selectedBarber->commissionAdjustments as $adjustment)<div class="flex justify-between gap-3 bg-amber-50 px-4 py-3 text-sm"><div><p class="font-bold">{{ $adjustment->type->label() }}</p><p class="text-xs text-slate-500">{{ $adjustment->reason }}</p></div><strong>{{ $adjustment->signedAmount() >= 0 ? '+' : '−' }}${{ number_format(abs($adjustment->signedAmount()), 2) }}</strong></div>@endforeach</div></div>
                <div class="grid gap-4 sm:grid-cols-2"><div><x-input-label for="settlement-payment" value="Forma de pago" /><select id="settlement-payment" wire:model="paymentMethod" class="mt-1 block w-full rounded-xl border-slate-300">@foreach ($paymentMethods as $method)<option value="{{ $method->value }}">{{ $method->label() }}</option>@endforeach</select><x-input-error :messages="$errors->get('paymentMethod')" class="mt-2" /></div><div><x-input-label for="settlement-reference" value="Referencia (opcional)" /><x-text-input id="settlement-reference" wire:model="paymentReference" class="mt-1 block w-full" /><x-input-error :messages="$errors->get('paymentReference')" class="mt-2" /></div></div>
                <div><x-input-label for="settlement-notes" value="Notas (opcional)" /><textarea id="settlement-notes" wire:model="settlementNotes" rows="2" class="mt-1 block w-full rounded-xl border-slate-300"></textarea><x-input-error :messages="$errors->get('settlementNotes')" class="mt-2" /></div>
            </div>
            <div class="flex justify-end gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4"><button type="button" wire:click="closeSettlement" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold">Cancelar</button><button type="submit" wire:confirm="¿Confirmas el pago de esta liquidación? Esta acción no se puede deshacer." class="rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-black text-white">Marcar como pagada</button></div>
        </form></div></div>
    @endif

    @if ($showAdjustmentModal)
        <div class="fixed inset-0 z-[75] overflow-y-auto px-4 py-6" role="dialog" aria-modal="true"><button type="button" wire:click="closeAdjustment" class="fixed inset-0 bg-slate-950/65" aria-label="Cerrar"></button><div class="relative mx-auto w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl"><form wire:submit="saveAdjustment">
            <div class="border-b border-slate-200 px-5 py-4"><p class="text-xs font-black uppercase tracking-[0.16em] text-amber-600">Ajuste autorizado</p><h3 class="mt-1 text-xl font-black">Bono o descuento</h3><p class="mt-1 text-sm text-slate-500">Se aplicará en la siguiente liquidación del barbero.</p></div>
            <div class="space-y-4 p-5"><div><x-input-label for="adjustment-type" value="Tipo" /><select id="adjustment-type" wire:model="adjustmentType" class="mt-1 block w-full rounded-xl border-slate-300">@foreach ($adjustmentTypes as $type)<option value="{{ $type->value }}">{{ $type->label() }}</option>@endforeach</select><x-input-error :messages="$errors->get('adjustmentType')" class="mt-2" /></div><div><x-input-label for="adjustment-amount" value="Importe" /><x-text-input id="adjustment-amount" wire:model="adjustmentAmount" type="number" min="0.01" step="0.01" class="mt-1 block w-full" /><x-input-error :messages="$errors->get('adjustmentAmount')" class="mt-2" /></div><div><x-input-label for="adjustment-reason" value="Motivo de autorización" /><textarea id="adjustment-reason" wire:model="adjustmentReason" rows="3" class="mt-1 block w-full rounded-xl border-slate-300"></textarea><x-input-error :messages="$errors->get('adjustmentReason')" class="mt-2" /></div></div>
            <div class="flex justify-end gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4"><button type="button" wire:click="closeAdjustment" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold">Cancelar</button><button type="submit" class="rounded-xl bg-amber-600 px-5 py-2.5 text-sm font-black text-white">Autorizar ajuste</button></div>
        </form></div></div>
    @endif
</div>
