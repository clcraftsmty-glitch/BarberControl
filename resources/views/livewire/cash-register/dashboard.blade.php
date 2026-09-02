<div>
    <x-slot name="header">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-600">Operación financiera</p>
            <h1 class="text-xl font-bold text-slate-900">Caja</h1>
        </div>
    </x-slot>

    @if (session('status'))
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
            {{ session('status') }}
            @if (session('sale_id'))
                <a href="{{ route('sales.show', session('sale_id')) }}" wire:navigate class="ml-2 font-extrabold underline underline-offset-2">Ver e imprimir ticket</a>
            @endif
        </div>
    @endif

    @error('cash_register')
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">{{ $message }}</div>
    @enderror
    @error('payment')
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">{{ $message }}</div>
    @enderror

    @if ($session)
        <section class="mb-6 overflow-hidden rounded-2xl border border-emerald-200 bg-white shadow-sm">
            <div class="flex flex-col gap-4 border-b border-emerald-100 bg-emerald-50/70 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div class="flex items-center gap-3">
                    <span class="relative flex h-3 w-3">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex h-3 w-3 rounded-full bg-emerald-500"></span>
                    </span>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-emerald-700">Caja abierta</p>
                        <p class="mt-0.5 text-sm text-slate-600">Desde {{ $session->opened_at->format('d/m/Y H:i') }} por {{ $session->opener?->name ?? 'Usuario eliminado' }}</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('cash-register.export', $session) }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">Exportar corte</a>
                    @can('adjust', $session)
                        <button type="button" wire:click="openMovementModal('ingreso')" class="rounded-xl border border-emerald-300 bg-white px-4 py-2.5 text-sm font-bold text-emerald-700 hover:bg-emerald-50">Registrar ingreso</button>
                        <button type="button" wire:click="openMovementModal('gasto')" class="rounded-xl border border-red-200 bg-white px-4 py-2.5 text-sm font-bold text-red-700 hover:bg-red-50">Registrar gasto</button>
                    @endcan
                    <button type="button" wire:click="openCloseModal" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-800">Cerrar caja</button>
                </div>
            </div>

            <div class="grid gap-px bg-slate-200 sm:grid-cols-2 xl:grid-cols-4">
                <div class="bg-white p-5">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Fondo inicial</p>
                    <p class="mt-2 text-2xl font-extrabold text-slate-900">${{ number_format((float) $session->opening_amount, 2) }}</p>
                </div>
                <div class="bg-white p-5">
                    <p class="text-xs font-bold uppercase tracking-wide text-emerald-600">Ingresos</p>
                    <p class="mt-2 text-2xl font-extrabold text-emerald-700">${{ number_format($income, 2) }}</p>
                </div>
                <div class="bg-white p-5">
                    <p class="text-xs font-bold uppercase tracking-wide text-red-600">Gastos</p>
                    <p class="mt-2 text-2xl font-extrabold text-red-700">${{ number_format($expenses, 2) }}</p>
                </div>
                <div class="bg-slate-900 p-5 text-white">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-300">Efectivo esperado</p>
                    <p class="mt-2 text-2xl font-extrabold">${{ number_format($expectedCash, 2) }}</p>
                    <p class="mt-1 text-xs text-slate-400">Fondo + ingresos en efectivo − gastos</p>
                </div>
            </div>
        </section>

        <div class="mb-6 grid gap-6 xl:grid-cols-[1.45fr_0.8fr]">
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 sm:px-6">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">Movimientos de la caja</h2>
                        <p class="mt-1 text-sm text-slate-500">Ingresos, gastos y cobros de esta apertura.</p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{{ $session->movements->count() }}</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-5 py-3 sm:px-6">Hora</th>
                                <th class="px-5 py-3">Concepto</th>
                                <th class="px-5 py-3">Categoría</th>
                                <th class="px-5 py-3">Método</th>
                                <th class="px-5 py-3 text-right sm:px-6">Importe</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($session->movements as $movement)
                                <tr>
                                    <td class="whitespace-nowrap px-5 py-3.5 font-semibold text-slate-600 sm:px-6">{{ $movement->occurred_at->format('H:i') }}</td>
                                    <td class="px-5 py-3.5">
                                        <p class="font-semibold text-slate-900">{{ $movement->description }}</p>
                                        <p class="mt-0.5 text-xs text-slate-500">{{ $movement->sale_id ? 'Cobro de servicio' : 'Movimiento manual' }} · {{ $movement->creator?->name ?? 'Usuario eliminado' }}</p>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3.5 text-slate-600">{{ $movement->category->label() }}</td>
                                    <td class="whitespace-nowrap px-5 py-3.5 text-slate-600">{{ $movement->payment_method->label() }}</td>
                                    <td class="whitespace-nowrap px-5 py-3.5 text-right font-extrabold {{ $movement->type === 'ingreso' ? 'text-emerald-700' : 'text-red-700' }} sm:px-6">
                                        {{ $movement->type === 'ingreso' ? '+' : '−' }}${{ number_format((float) $movement->amount, 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-10 text-center text-sm text-slate-500">Aún no hay movimientos en esta caja.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-lg font-bold text-slate-900">Desglose por método</h2>
                    <p class="mt-1 text-sm text-slate-500">Ingresos, salidas y saldo neto.</p>
                </div>
                <div class="divide-y divide-slate-100">
                    @foreach ($paymentMethods as $method)
                        @php
                            $totals = $paymentBreakdown->get($method->value);
                        @endphp
                        <div class="px-5 py-4">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-bold text-slate-800">{{ $method->label() }}</span>
                                <span class="font-extrabold text-slate-900">${{ number_format($totals['net'], 2) }}</span>
                            </div>
                            <div class="mt-1 flex justify-between text-xs">
                                <span class="text-emerald-700">Ingresos ${{ number_format($totals['income'], 2) }}</span>
                                <span class="text-red-700">Gastos ${{ number_format($totals['expenses'], 2) }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        <section class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4 sm:px-6">
                <h2 class="text-lg font-bold text-slate-900">Ingresos y gastos por categoría</h2>
                <p class="mt-1 text-sm text-slate-500">Clasificación de los movimientos de esta apertura.</p>
            </div>
            <div class="grid gap-px bg-slate-200 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($categoryBreakdown as $item)
                    <div class="flex items-center justify-between bg-white px-5 py-4">
                        <div>
                            <p class="font-semibold text-slate-800">{{ $item['category']->label() }}</p>
                            <p class="text-xs {{ $item['type'] === 'ingreso' ? 'text-emerald-700' : 'text-red-700' }}">{{ ucfirst($item['type']) }}</p>
                        </div>
                        <p class="font-extrabold {{ $item['type'] === 'ingreso' ? 'text-emerald-700' : 'text-red-700' }}">${{ number_format($item['total'], 2) }}</p>
                    </div>
                @empty
                    <p class="col-span-full bg-white px-5 py-8 text-center text-sm text-slate-500">Aún no hay movimientos para clasificar.</p>
                @endforelse
            </div>
        </section>

        <section class="mb-6 overflow-hidden rounded-2xl border border-orange-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-orange-100 bg-orange-50/70 px-5 py-4 sm:px-6">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Servicios pendientes de cobro</h2>
                    <p class="mt-1 text-sm text-slate-500">Cobra el servicio para crear venta, movimiento y comisión.</p>
                </div>
                <span class="rounded-full bg-orange-100 px-3 py-1 text-xs font-bold text-orange-800">{{ $pendingAppointments->count() }}</span>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse ($pendingAppointments as $appointment)
                    <article class="grid gap-3 px-5 py-4 sm:grid-cols-[90px_1fr_1fr_auto] sm:items-center sm:px-6">
                        <div>
                            <p class="font-extrabold text-slate-900">{{ $appointment->starts_at->format('H:i') }}</p>
                            <p class="text-xs text-slate-500">{{ $appointment->starts_at->format('d/m/Y') }}</p>
                        </div>
                        <div>
                            <p class="font-bold text-slate-900">{{ $appointment->client->full_name }}</p>
                            <p class="text-xs text-slate-500">{{ $appointment->client->phone }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-slate-800">{{ $appointment->service->name }}</p>
                            <p class="text-xs text-slate-500">{{ $appointment->barber->display_name }}</p>
                        </div>
                        <div class="flex items-center justify-between gap-4 sm:justify-end">
                            <span class="font-extrabold text-slate-900">${{ number_format((float) $appointment->price, 2) }}</span>
                            <button type="button" wire:click="openPayment({{ $appointment->id }})" class="rounded-xl bg-orange-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-orange-700">Cobrar</button>
                        </div>
                    </article>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-slate-500">No hay servicios pendientes de cobro.</p>
                @endforelse
            </div>
        </section>
    @else
        <section class="mb-6 rounded-2xl border border-slate-200 bg-white px-6 py-12 text-center shadow-sm">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-slate-500">
                <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4.5 7.5h15m-12-3h9A1.5 1.5 0 0 1 18 6v12a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 6 18V6a1.5 1.5 0 0 1 1.5-1.5Zm3 7.5h3" /></svg>
            </div>
            <h2 class="mt-4 text-xl font-extrabold text-slate-900">No hay una caja abierta</h2>
            <p class="mx-auto mt-2 max-w-lg text-sm text-slate-500">Abre la caja con el efectivo inicial para comenzar a registrar ingresos, gastos y cobros de servicios.</p>
            <button type="button" wire:click="openRegisterModal" class="mt-6 rounded-xl bg-brand-600 px-5 py-3 text-sm font-bold text-white shadow-sm hover:bg-brand-700">Abrir caja</button>
        </section>
    @endif

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4 sm:px-6">
            <h2 class="text-lg font-bold text-slate-900">Historial de aperturas y cierres</h2>
            <p class="mt-1 text-sm text-slate-500">Conciliación, responsables, ajustes autorizados y exportación.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3 sm:px-6">Apertura / cierre</th>
                        <th class="px-5 py-3">Responsables</th>
                        <th class="px-5 py-3 text-right">Esperado</th>
                        <th class="px-5 py-3 text-right">Real</th>
                        <th class="px-5 py-3 text-right">Diferencia</th>
                        <th class="px-5 py-3 text-right sm:px-6">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($sessionHistory as $closure)
                        <tr>
                            <td class="whitespace-nowrap px-5 py-3.5 sm:px-6">
                                <p class="font-semibold text-slate-800">{{ $closure->opened_at->format('d/m/Y H:i') }}</p>
                                <p class="text-xs text-slate-500">{{ $closure->closed_at ? 'a '.$closure->closed_at->format('d/m/Y H:i') : 'Caja abierta' }}</p>
                            </td>
                            <td class="px-5 py-3.5 text-slate-600">
                                <p>{{ $closure->opener?->name ?? 'Usuario eliminado' }} / {{ $closure->closer?->name ?? 'Pendiente' }}</p>
                                <p class="mt-0.5 text-xs text-slate-400">{{ $closure->movements_count }} movimientos</p>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5 text-right font-semibold">{{ $closure->expected_cash !== null ? '$'.number_format((float) $closure->expected_cash, 2) : 'Pendiente' }}</td>
                            <td class="whitespace-nowrap px-5 py-3.5 text-right font-semibold">{{ $closure->actual_cash !== null ? '$'.number_format((float) $closure->actual_cash, 2) : 'Pendiente' }}</td>
                            <td class="whitespace-nowrap px-5 py-3.5 text-right font-extrabold {{ (float) $closure->difference === 0.0 ? 'text-emerald-700' : ((float) $closure->difference > 0 ? 'text-blue-700' : 'text-red-700') }} sm:px-6">
                                {{ (float) $closure->difference > 0 ? '+' : ((float) $closure->difference < 0 ? '−' : '') }}${{ number_format(abs((float) $closure->difference), 2) }}
                                @if ($closure->difference_authorized_by)
                                    <p class="mt-0.5 text-[11px] font-medium text-slate-500">Autorizó {{ $closure->differenceAuthorizer?->name }}</p>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5 text-right sm:px-6"><a href="{{ route('cash-register.export', $closure) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Exportar</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-8 text-center text-sm text-slate-500">Todavía no hay aperturas de caja.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($sessionHistory->hasPages())
            <div class="border-t border-slate-200 px-5 py-4">{{ $sessionHistory->links() }}</div>
        @endif
    </section>

    @if ($unassignedMovements->isNotEmpty())
        <section class="mt-6 overflow-hidden rounded-2xl border border-amber-200 bg-white shadow-sm">
            <div class="border-b border-amber-100 bg-amber-50/70 px-5 py-4 sm:px-6">
                <h2 class="text-lg font-bold text-slate-900">Movimientos anteriores sin apertura asociada</h2>
                <p class="mt-1 text-sm text-amber-800">Son cobros registrados antes de habilitar el módulo de Caja. Se conservan como historial y no alteran el arqueo actual.</p>
            </div>
            <div class="divide-y divide-slate-100">
                @foreach ($unassignedMovements as $movement)
                    <div class="grid gap-2 px-5 py-4 sm:grid-cols-[130px_1fr_140px] sm:items-center sm:px-6">
                        <div><p class="font-semibold text-slate-700">{{ $movement->occurred_at->format('d/m/Y H:i') }}</p></div>
                        <div><p class="font-semibold text-slate-900">{{ $movement->description }}</p><p class="text-xs text-slate-500">{{ $movement->creator?->name ?? 'Usuario eliminado' }} · {{ $movement->payment_method->label() }}</p></div>
                        <p class="text-right font-extrabold {{ $movement->type === 'ingreso' ? 'text-emerald-700' : 'text-red-700' }}">{{ $movement->type === 'ingreso' ? '+' : '−' }}${{ number_format((float) $movement->amount, 2) }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($showOpenModal)
        <div class="fixed inset-0 z-[70] overflow-y-auto px-4 py-6" role="dialog" aria-modal="true">
            <button type="button" wire:click="closeModal('showOpenModal')" class="fixed inset-0 bg-slate-950/60" aria-label="Cerrar"></button>
            <div class="relative mx-auto w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
                <form wire:submit="openRegister">
                    <div class="border-b border-slate-200 px-5 py-4 sm:px-6"><h3 class="text-xl font-bold text-slate-900">Abrir caja</h3><p class="mt-1 text-sm text-slate-500">Registra el efectivo disponible al iniciar.</p></div>
                    <div class="space-y-5 p-5 sm:p-6">
                        <div><x-input-label for="opening-amount" value="Fondo inicial" /><div class="relative mt-1"><span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-500">$</span><x-text-input id="opening-amount" wire:model="opening_amount" type="number" min="0" step="0.01" class="block w-full pl-7" /></div><x-input-error :messages="$errors->get('opening_amount')" class="mt-2" /></div>
                        <div><x-input-label for="opening-notes" value="Notas (opcional)" /><textarea id="opening-notes" wire:model="opening_notes" rows="3" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500"></textarea><x-input-error :messages="$errors->get('opening_notes')" class="mt-2" /></div>
                    </div>
                    <div class="flex justify-end gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4"><button type="button" wire:click="closeModal('showOpenModal')" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700">Cancelar</button><button type="submit" class="rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-white">Abrir caja</button></div>
                </form>
            </div>
        </div>
    @endif

    @if ($showMovementModal)
        <div class="fixed inset-0 z-[70] overflow-y-auto px-4 py-6" role="dialog" aria-modal="true">
            <button type="button" wire:click="closeModal('showMovementModal')" class="fixed inset-0 bg-slate-950/60" aria-label="Cerrar"></button>
            <div class="relative mx-auto w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
                <form wire:submit="recordMovement">
                    <div class="border-b border-slate-200 px-5 py-4 sm:px-6"><h3 class="text-xl font-bold text-slate-900">Registrar {{ $movement_type }}</h3><p class="mt-1 text-sm text-slate-500">Este movimiento se registrará en efectivo.</p></div>
                    <div class="space-y-5 p-5 sm:p-6">
                        <div><x-input-label for="movement-amount" value="Importe" /><div class="relative mt-1"><span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-500">$</span><x-text-input id="movement-amount" wire:model="movement_amount" type="number" min="0.01" step="0.01" class="block w-full pl-7" /></div><x-input-error :messages="$errors->get('movement_amount')" class="mt-2" /></div>
                        <div><x-input-label for="movement-category" value="Categoría" /><select id="movement-category" wire:model="movement_category" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">@foreach ($movementCategories as $category)<option value="{{ $category->value }}">{{ $category->label() }}{{ $category->requiresAdministrator() ? ' · requiere administrador' : '' }}</option>@endforeach</select><x-input-error :messages="$errors->get('movement_category')" class="mt-2" /></div>
                        <div><x-input-label for="movement-description" value="Concepto" /><x-text-input id="movement-description" wire:model="movement_description" type="text" class="mt-1 block w-full" placeholder="Ej. Compra de insumos" /><x-input-error :messages="$errors->get('movement_description')" class="mt-2" /></div>
                    </div>
                    <div class="flex justify-end gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4"><button type="button" wire:click="closeModal('showMovementModal')" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700">Cancelar</button><button type="submit" class="rounded-xl px-5 py-2.5 text-sm font-bold text-white {{ $movement_type === 'ingreso' ? 'bg-emerald-600' : 'bg-red-600' }}">Registrar</button></div>
                </form>
            </div>
        </div>
    @endif

    @if ($showCloseModal && $session)
        <div class="fixed inset-0 z-[70] overflow-y-auto px-4 py-6" role="dialog" aria-modal="true">
            <button type="button" wire:click="closeModal('showCloseModal')" class="fixed inset-0 bg-slate-950/60" aria-label="Cerrar"></button>
            <div class="relative mx-auto w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
                <form wire:submit="closeRegister">
                    <div class="border-b border-slate-200 px-5 py-4 sm:px-6"><h3 class="text-xl font-bold text-slate-900">Cerrar caja</h3><p class="mt-1 text-sm text-slate-500">Cuenta físicamente el efectivo antes de continuar.</p></div>
                    <div class="space-y-5 p-5 sm:p-6">
                        <div class="rounded-xl bg-slate-900 px-4 py-4 text-white"><p class="text-xs font-bold uppercase tracking-wide text-slate-300">Efectivo esperado</p><p class="mt-1 text-2xl font-extrabold">${{ number_format($expectedCash, 2) }}</p></div>
                        <div><x-input-label for="actual-cash" value="Efectivo real contado" /><div class="relative mt-1"><span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-500">$</span><x-text-input id="actual-cash" wire:model.live.debounce.300ms="actual_cash" type="number" min="0" step="0.01" class="block w-full pl-7" /></div><x-input-error :messages="$errors->get('actual_cash')" class="mt-2" /></div>
                        @if (is_numeric($actual_cash))
                            @php
                                $previewDifference = round((float) $actual_cash - $expectedCash, 2);
                            @endphp
                            <div class="rounded-xl px-4 py-3 {{ $previewDifference === 0.0 ? 'bg-emerald-50 text-emerald-800' : ($previewDifference > 0 ? 'bg-blue-50 text-blue-800' : 'bg-red-50 text-red-800') }}"><p class="text-xs font-bold uppercase tracking-wide">Diferencia</p><p class="mt-1 text-xl font-extrabold">{{ $previewDifference > 0 ? '+' : ($previewDifference < 0 ? '−' : '') }}${{ number_format(abs($previewDifference), 2) }}</p></div>
                            @if (abs($previewDifference) >= 0.01)
                                <div>
                                    <x-input-label for="difference-reason" value="Motivo de la diferencia y autorización" />
                                    <textarea id="difference-reason" wire:model="difference_reason" rows="3" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" placeholder="Explica el sobrante o faltante detectado"></textarea>
                                    <x-input-error :messages="$errors->get('difference_reason')" class="mt-2" />
                                    <p class="mt-2 text-xs text-slate-500">La conciliación con diferencia sólo puede ser confirmada por un administrador y quedará en el historial.</p>
                                </div>
                            @endif
                        @endif
                        <div><x-input-label for="closing-notes" value="Notas de cierre (opcional)" /><textarea id="closing-notes" wire:model="closing_notes" rows="3" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500"></textarea><x-input-error :messages="$errors->get('closing_notes')" class="mt-2" /></div>
                    </div>
                    <div class="flex justify-end gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4"><button type="button" wire:click="closeModal('showCloseModal')" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700">Cancelar</button><button type="submit" class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white">Confirmar cierre</button></div>
                </form>
            </div>
        </div>
    @endif

    @if ($showPaymentModal && $paymentAppointment)
        <div class="fixed inset-0 z-[70] overflow-y-auto px-4 py-6" role="dialog" aria-modal="true">
            <button type="button" wire:click="closeModal('showPaymentModal')" class="fixed inset-0 bg-slate-950/60" aria-label="Cerrar"></button>
            <div class="relative mx-auto w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
                <form wire:submit="registerPayment">
                    <div class="border-b border-slate-200 px-5 py-4 sm:px-6"><p class="text-xs font-bold uppercase tracking-[0.18em] text-orange-600">Cobro de servicio</p><h3 class="mt-1 text-xl font-bold text-slate-900">{{ $paymentAppointment->client->full_name }}</h3><p class="mt-1 text-sm text-slate-500">{{ $paymentAppointment->service->name }} · {{ $paymentAppointment->barber->display_name }}</p></div>
                    <div class="space-y-5 p-5 sm:p-6">
                        <div class="rounded-xl bg-slate-50 px-4 py-4"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Total</p><p class="mt-1 text-3xl font-extrabold text-slate-900">${{ number_format((float) $paymentAppointment->price, 2) }}</p></div>
                        <div><x-input-label for="cash-payment-method" value="Método de pago" /><select id="cash-payment-method" wire:model="payment_method" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">@foreach ($paymentMethods as $method)<option value="{{ $method->value }}">{{ $method->label() }}</option>@endforeach</select><x-input-error :messages="$errors->get('payment_method')" class="mt-2" /></div>
                        <div><x-input-label for="cash-payment-reference" value="Referencia (opcional)" /><x-text-input id="cash-payment-reference" wire:model="payment_reference" type="text" class="mt-1 block w-full" /><x-input-error :messages="$errors->get('payment_reference')" class="mt-2" /></div>
                    </div>
                    <div class="flex justify-end gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4"><button type="button" wire:click="closeModal('showPaymentModal')" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700">Cancelar</button><button type="submit" class="rounded-xl bg-orange-600 px-5 py-2.5 text-sm font-bold text-white">Cobrar y terminar</button></div>
                </form>
            </div>
        </div>
    @endif

    <livewire:sales.receipt-delivery />
</div>
