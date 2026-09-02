<div wire:poll.30s>
    <x-slot name="header">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-600">Operación en tiempo real</p>
            <h1 class="truncate text-lg font-bold text-slate-900">Dashboard operativo</h1>
        </div>
    </x-slot>

    <div class="mx-auto max-w-[1600px] space-y-5">
        <section class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-brand-600">{{ now()->translatedFormat('l d \d\e F') }}</p>
                <h2 class="mt-1 text-2xl font-black tracking-tight text-slate-950">Así marcha la barbería hoy</h2>
                <p class="mt-1 text-sm text-slate-500">Prioriza esperas, servicios activos, cobros y citas atrasadas.</p>
            </div>
            <div class="flex items-center gap-3">
                <p class="text-right text-xs text-slate-500">
                    <span class="block font-bold text-slate-700">Actualización automática</span>
                    Cada 30 segundos · {{ $refreshedAt->format('H:i:s') }}
                </p>
                <button wire:click="$refresh" wire:loading.attr="disabled" type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:border-brand-300 hover:text-brand-700 disabled:opacity-50" title="Actualizar ahora">
                    <svg wire:loading.class="animate-spin" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 11a8.1 8.1 0 0 0-15.5-2M4 4v5h5m-5 4a8.1 8.1 0 0 0 15.5 2m.5 5v-5h-5" /></svg>
                </button>
            </div>
        </section>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <a href="{{ route('appointments.index') }}" wire:navigate class="group rounded-2xl border border-blue-200 bg-blue-50 p-5 transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="flex items-start justify-between">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-500 text-white"><svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6.75 3v2.25M17.25 3v2.25M3.75 9h16.5m-15 12h13.5a1.5 1.5 0 0 0 1.5-1.5V6.75a1.5 1.5 0 0 0-1.5-1.5H5.25a1.5 1.5 0 0 0-1.5 1.5V19.5a1.5 1.5 0 0 0 1.5 1.5Z" /></svg></span>
                    <strong class="text-3xl font-black text-blue-950">{{ $appointmentsToday }}</strong>
                </div>
                <h3 class="mt-4 text-base font-extrabold text-blue-950">Citas de hoy</h3>
                <p class="mt-1 text-xs font-medium text-blue-700">{{ $completedToday }} terminadas</p>
            </a>

            <a href="{{ route('appointments.index', ['group' => 'waiting']) }}" wire:navigate class="group rounded-2xl border border-cyan-200 bg-cyan-50 p-5 transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="flex items-start justify-between">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-cyan-500 text-white"><svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.1a7.5 7.5 0 0 1 15 0M18 8.25v6m3-3h-6" /></svg></span>
                    <strong class="text-3xl font-black text-cyan-950">{{ $waitingClients }}</strong>
                </div>
                <h3 class="mt-4 text-base font-extrabold text-cyan-950">Clientes esperando</h3>
                <p class="mt-1 text-xs font-medium text-cyan-700">{{ $waitingAppointments }} con cita · {{ $waitingWalkIns }} sin cita</p>
            </a>

            <a href="{{ route('appointments.index', ['group' => 'in_service']) }}" wire:navigate class="group rounded-2xl border border-violet-200 bg-violet-50 p-5 transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="flex items-start justify-between">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-violet-500 text-white"><svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m14.25 6.087-6.587 6.587a2.25 2.25 0 1 0 3.182 3.182l6.587-6.587M6.75 6.75l10.5 10.5M5.25 4.5l14.25 14.25" /></svg></span>
                    <strong class="text-3xl font-black text-violet-950">{{ $servicesInProgress }}</strong>
                </div>
                <h3 class="mt-4 text-base font-extrabold text-violet-950">Servicios en proceso</h3>
                <p class="mt-1 text-xs font-medium text-violet-700">Atenciones activas ahora</p>
            </a>

            <a href="{{ route('appointments.index', ['group' => 'pending_payment']) }}" wire:navigate class="group rounded-2xl border border-orange-200 bg-orange-50 p-5 transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="flex items-start justify-between">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-orange-500 text-white"><svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.25 8.25h19.5m-18 0V18A2.25 2.25 0 0 0 6 20.25h12A2.25 2.25 0 0 0 20.25 18V8.25M6.75 12h4.5" /></svg></span>
                    <strong class="text-3xl font-black text-orange-950">{{ $pendingPayments }}</strong>
                </div>
                <h3 class="mt-4 text-base font-extrabold text-orange-950">Cobros pendientes</h3>
                <p class="mt-1 text-xs font-medium text-orange-700">Servicios que requieren pago</p>
            </a>
        </section>

        <section class="grid gap-3 sm:grid-cols-2 {{ $canSeeFinancials ? 'xl:grid-cols-4' : 'xl:grid-cols-2' }}">
            @if ($canSeeFinancials)
                <a href="{{ route('cash-register.index') }}" wire:navigate class="rounded-2xl border p-5 shadow-sm transition hover:shadow-md {{ $cashIsOpen ? 'border-emerald-200 bg-emerald-50' : 'border-red-200 bg-red-50' }}">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-extrabold {{ $cashIsOpen ? 'text-emerald-900' : 'text-red-900' }}">Estado de caja</p>
                        <span class="rounded-full px-3 py-1 text-xs font-black uppercase {{ $cashIsOpen ? 'bg-emerald-200 text-emerald-800' : 'bg-red-200 text-red-800' }}">{{ $cashIsOpen ? 'Abierta' : 'Cerrada' }}</span>
                    </div>
                    <p class="mt-4 text-2xl font-black text-slate-950">${{ number_format($expectedCash, 2) }}</p>
                    <p class="mt-1 text-xs text-slate-600">Efectivo esperado {{ $cashSession?->opener ? '· abrió '.$cashSession->opener->name : '' }}</p>
                </a>

                <a href="{{ route('sales.index') }}" wire:navigate class="rounded-2xl border border-emerald-200 bg-white p-5 shadow-sm transition hover:shadow-md">
                    <div class="flex items-center justify-between"><p class="text-sm font-extrabold text-slate-700">Ventas del día</p><span class="text-xs font-bold text-emerald-700">{{ $salesCount }} ventas</span></div>
                    <p class="mt-4 text-2xl font-black text-slate-950">${{ number_format($salesToday, 2) }}</p>
                    <p class="mt-1 text-xs text-slate-500">Ventas completadas</p>
                </a>
            @endif

            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between"><p class="text-sm font-extrabold text-slate-700">Tiempo promedio de espera</p><svg class="h-5 w-5 text-cyan-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6v6h4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg></div>
                <p class="mt-4 text-2xl font-black text-slate-950">{{ (int) round($averageWaitSeconds / 60) }} min</p>
                <p class="mt-1 text-xs text-slate-500">Desde llegada hasta iniciar servicio</p>
            </article>

            <article class="rounded-2xl border p-5 shadow-sm {{ $lateAppointments->isEmpty() ? 'border-slate-200 bg-white' : 'border-red-200 bg-red-50' }}">
                <div class="flex items-center justify-between"><p class="text-sm font-extrabold {{ $lateAppointments->isEmpty() ? 'text-slate-700' : 'text-red-900' }}">Citas atrasadas</p><span class="flex h-7 min-w-7 items-center justify-center rounded-full px-2 text-xs font-black {{ $lateAppointments->isEmpty() ? 'bg-emerald-100 text-emerald-700' : 'bg-red-200 text-red-800' }}">{{ $lateAppointments->count() }}</span></div>
                <p class="mt-4 text-2xl font-black {{ $lateAppointments->isEmpty() ? 'text-emerald-700' : 'text-red-900' }}">{{ $lateAppointments->isEmpty() ? 'Al corriente' : 'Requieren atención' }}</p>
                <p class="mt-1 text-xs {{ $lateAppointments->isEmpty() ? 'text-slate-500' : 'text-red-700' }}">Pendientes o confirmadas después de su hora</p>
            </article>
        </section>

        @if ($lateAppointments->isNotEmpty())
            <section class="overflow-hidden rounded-2xl border border-red-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-red-100 bg-red-50 px-5 py-4">
                    <div><h3 class="font-black text-red-950">Alertas de citas atrasadas</h3><p class="mt-0.5 text-xs text-red-700">Contacta al cliente o actualiza el estado de la cita.</p></div>
                    <a href="{{ route('appointments.index') }}" wire:navigate class="rounded-xl bg-red-600 px-4 py-2 text-xs font-bold text-white hover:bg-red-700">Ir a la agenda</a>
                </div>
                <div class="divide-y divide-slate-100">
                    @foreach ($lateAppointments as $appointment)
                        <article class="grid gap-3 px-5 py-4 sm:grid-cols-[80px_1fr_1fr_auto] sm:items-center">
                            <div><p class="text-lg font-black text-slate-950">{{ $appointment->starts_at->format('H:i') }}</p><p class="text-xs font-bold text-red-600">{{ $appointment->late_minutes }} min tarde</p></div>
                            <div><p class="font-bold text-slate-900">{{ $appointment->client->full_name }}</p><p class="text-xs text-slate-500">{{ $appointment->client->phone }}</p></div>
                            <div><p class="text-sm font-bold text-slate-800">{{ $appointment->service->name }}</p><p class="text-xs text-slate-500">{{ $appointment->barber->display_name }}</p></div>
                            <span class="w-fit rounded-full px-2.5 py-1 text-[10px] font-extrabold uppercase {{ $appointment->status->badgeClasses() }}">{{ $appointment->status->label() }}</span>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="grid gap-5 xl:grid-cols-[minmax(0,1.5fr)_minmax(320px,.7fr)]">
            <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <div><h3 class="font-black text-slate-950">Operación activa</h3><p class="mt-0.5 text-xs text-slate-500">Próximas citas y servicios que todavía no terminan.</p></div>
                    <a href="{{ route('appointments.index') }}" wire:navigate class="text-xs font-bold text-brand-700 hover:text-brand-800">Ver orden del día</a>
                </div>
                @if ($activeAppointments->isEmpty())
                    <div class="px-6 py-12 text-center"><p class="font-bold text-slate-700">No hay citas activas para hoy.</p><p class="mt-1 text-sm text-slate-500">La operación del día está al corriente.</p></div>
                @else
                    <div class="divide-y divide-slate-100">
                        @foreach ($activeAppointments->take(10) as $appointment)
                            <article class="grid gap-3 px-5 py-4 md:grid-cols-[72px_1.1fr_1fr_140px] md:items-center">
                                <div><p class="text-lg font-black text-slate-950">{{ $appointment->starts_at->format('H:i') }}</p><p class="text-xs text-slate-500">a {{ $appointment->ends_at->format('H:i') }}</p></div>
                                <div><p class="font-bold text-slate-900">{{ $appointment->client->full_name }}</p><p class="text-xs text-slate-500">{{ $appointment->client->phone }}</p></div>
                                <div><p class="text-sm font-bold text-slate-800">{{ $appointment->service->name }}</p><p class="text-xs text-slate-500">{{ $appointment->barber->display_name }}</p></div>
                                <span class="w-fit rounded-full px-2.5 py-1 text-[10px] font-extrabold uppercase {{ $appointment->status->badgeClasses() }}">{{ $appointment->status->label() }}</span>
                            </article>
                        @endforeach
                    </div>
                @endif
            </article>

            <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4"><h3 class="font-black text-slate-950">Tiempo promedio por barbero</h3><p class="mt-0.5 text-xs text-slate-500">Servicios finalizados durante el día.</p></div>
                @if ($barberPerformance->isEmpty())
                    <div class="px-6 py-12 text-center"><p class="font-bold text-slate-700">Aún no hay servicios finalizados.</p><p class="mt-1 text-sm text-slate-500">Los promedios aparecerán al terminar servicios.</p></div>
                @else
                    <div class="divide-y divide-slate-100">
                        @foreach ($barberPerformance as $performance)
                            <div class="flex items-center gap-4 px-5 py-4">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-violet-100 font-black text-violet-700">{{ str($performance['name'])->substr(0, 1)->upper() }}</span>
                                <div class="min-w-0 flex-1"><p class="truncate font-bold text-slate-900">{{ $performance['name'] }}</p><p class="text-xs text-slate-500">{{ $performance['services'] }} servicio{{ $performance['services'] === 1 ? '' : 's' }} medido{{ $performance['services'] === 1 ? '' : 's' }}</p></div>
                                <strong class="text-lg font-black text-slate-950">{{ (int) round($performance['average_seconds'] / 60) }} min</strong>
                            </div>
                        @endforeach
                    </div>
                @endif
            </article>
        </section>
    </div>
</div>
