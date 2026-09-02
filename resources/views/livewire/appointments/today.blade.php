<div>
    <x-slot name="header">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-600">Operación diaria</p>
            <h1 class="text-xl font-bold text-slate-900">Agenda de citas</h1>
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

    @error('status')
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">{{ $message }}</div>
    @enderror
    @error('payment')
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">{{ $message }}</div>
    @enderror

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <nav class="inline-flex rounded-xl border border-slate-200 bg-white p-1 shadow-sm" aria-label="Vistas de agenda">
            <a href="{{ route('appointments.index') }}" wire:navigate class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-bold text-white">Hoy</a>
            <a href="{{ route('appointments.calendar') }}" wire:navigate class="rounded-lg px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-100">Calendario</a>
        </nav>

        <div class="flex flex-wrap gap-2">
            @can('create', \App\Models\WalkInEntry::class)
                @if ($isSelectedToday)
                    <button type="button" wire:click="openWalkIn" class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-cyan-600 px-4 py-2.5 text-sm font-extrabold text-white shadow-sm hover:bg-cyan-700">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 18.72a9.094 9.094 0 0 0 3.742-.479 3 3 0 0 0-4.682-2.72m.94 3.198v.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.205-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.94-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.06 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197A5.971 5.971 0 0 0 6 18.719M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" /></svg>
                        Cliente sin cita
                    </button>
                @endif
            @endcan

            @can('create', \App\Models\Appointment::class)
                <a href="{{ route('appointments.calendar', ['new' => 1]) }}" wire:navigate class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-extrabold text-white shadow-sm hover:bg-brand-700">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    Nueva cita
                </a>
            @endcan
        </div>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5 2xl:p-8">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between 2xl:gap-5">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" wire:click="previousDay" class="flex h-11 w-11 items-center justify-center rounded-xl border border-slate-300 bg-white text-slate-700 shadow-sm hover:bg-slate-100" aria-label="Día anterior">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 18-6-6 6-6" /></svg>
                    </button>

                    <label class="relative">
                        <span class="sr-only">Seleccionar fecha</span>
                        <input type="date" wire:model.live="selectedDate" class="min-h-11 rounded-xl border-slate-300 bg-white py-2.5 pl-4 pr-3 text-sm font-extrabold uppercase tracking-wide text-brand-700 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-base">
                    </label>

                    <button type="button" wire:click="nextDay" class="flex h-11 w-11 items-center justify-center rounded-xl border border-slate-300 bg-white text-slate-700 shadow-sm hover:bg-slate-100" aria-label="Día siguiente">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 18 6-6-6-6" /></svg>
                    </button>

                    @if ($isSelectedToday)
                        <span x-data="{ time: new Date().toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' }) }" x-init="setInterval(() => time = new Date().toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' }), 30000)" class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-slate-900 px-4 py-2 text-base font-black text-white shadow-sm 2xl:text-lg">
                            <svg class="h-5 w-5 text-brand-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                            <span x-text="time"></span>
                        </span>
                    @else
                        <button type="button" wire:click="goToday" class="min-h-11 rounded-xl bg-brand-50 px-4 py-2 text-sm font-extrabold text-brand-700 ring-1 ring-brand-200 hover:bg-brand-100">Volver a hoy</button>
                    @endif
                </div>
                <p class="mt-3 text-xs font-extrabold uppercase tracking-[0.18em] text-brand-600 sm:text-sm 2xl:mt-4 2xl:text-base 2xl:tracking-[0.2em]">{{ $viewDate->translatedFormat('l d \d\e F \d\e Y') }}</p>
                <h2 class="mt-1.5 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl 2xl:mt-2 2xl:text-4xl">Orden del día</h2>
                <p class="mt-1.5 text-sm font-medium text-slate-600 sm:text-base 2xl:mt-2 2xl:text-lg">{{ $isSelectedToday ? 'Atiende primero espera, servicios en curso y cobros pendientes.' : 'Consulta histórica en modo de solo lectura.' }}</p>

                <div class="mt-4 flex flex-wrap gap-2 text-xs font-extrabold sm:text-sm 2xl:mt-5 2xl:gap-2.5 2xl:text-base">
                    <span class="rounded-full bg-slate-100 px-3 py-1.5 text-slate-700 2xl:px-4 2xl:py-2"><strong class="text-slate-950">{{ $remainingCount }}</strong> restantes</span>
                    <span class="rounded-full bg-cyan-100 px-3 py-1.5 text-cyan-800 2xl:px-4 2xl:py-2"><strong>{{ $groupCounts->get('waiting') }}</strong> esperando</span>
                    <span class="rounded-full bg-violet-100 px-3 py-1.5 text-violet-800 2xl:px-4 2xl:py-2"><strong>{{ $groupCounts->get('in_service') }}</strong> en servicio</span>
                    <span class="rounded-full bg-orange-100 px-3 py-1.5 text-orange-800 2xl:px-4 2xl:py-2"><strong>{{ $groupCounts->get('pending_payment') }}</strong> por cobrar</span>
                    @if ($isSelectedToday)
                        <span class="rounded-full bg-teal-100 px-3 py-1.5 text-teal-800 2xl:px-4 2xl:py-2"><strong>{{ $walkInEntries->count() }}</strong> sin cita en fila</span>
                    @endif
                </div>
            </div>

            @if (auth()->user()->role !== \App\Enums\UserRole::Barber)
                <div class="w-full lg:w-56 2xl:w-64">
                    <label for="today-barber-filter" class="mb-1.5 block text-xs font-extrabold uppercase tracking-wide text-slate-600 2xl:mb-2 2xl:text-sm">Barbero</label>
                    <select id="today-barber-filter" wire:model.live="barberFilter" class="w-full rounded-xl border-slate-300 py-2.5 text-sm font-semibold shadow-sm focus:border-brand-500 focus:ring-brand-500 2xl:py-3 2xl:text-base">
                        <option value="">Todos los barberos</option>
                        @foreach ($barbers as $barber)
                            <option value="{{ $barber->id }}">{{ $barber->display_name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>
    </section>

    @php
        $summaryConfiguration = [
            'upcoming' => ['Próximas', 'Pendientes o confirmadas', 'bg-blue-500', 'border-blue-200 bg-blue-50 text-blue-900'],
            'waiting' => ['En espera', 'Ya llegaron', 'bg-cyan-500', 'border-cyan-200 bg-cyan-50 text-cyan-900'],
            'in_service' => ['En servicio', 'En curso', 'bg-violet-500', 'border-violet-200 bg-violet-50 text-violet-900'],
            'pending_payment' => ['Por cobrar', 'Requieren pago', 'bg-orange-500', 'border-orange-200 bg-orange-50 text-orange-900'],
            'finished' => ['Finalizadas', $isSelectedToday ? 'Cerradas hoy' : 'Cerradas ese día', 'bg-emerald-500', 'border-emerald-200 bg-emerald-50 text-emerald-900'],
        ];
        $groupConfiguration = [
            'waiting' => ['En espera', 'Clientes que ya llegaron', 'bg-cyan-500'],
            'in_service' => ['En servicio', 'Servicios actualmente en curso', 'bg-violet-500'],
            'pending_payment' => ['Pendientes de cobro', 'Servicios terminados que requieren pago', 'bg-orange-500'],
            'upcoming' => ['Próximas', 'Citas pendientes o confirmadas', 'bg-blue-500'],
            'finished' => ['Finalizadas', 'Terminadas, canceladas, no asistidas o reprogramadas', 'bg-emerald-500'],
        ];
        $visibleGroupKeys = $groupFilter === 'all'
            ? collect(array_keys($groupConfiguration))->filter(fn ($key) => $groupCounts->get($key) > 0)
            : collect([$groupFilter])->filter(fn ($key) => $groupCounts->get($key, 0) > 0);
    @endphp

    <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-5 2xl:mt-5 2xl:gap-4">
        @foreach ($summaryConfiguration as $groupKey => [$label, $subtitle, $dotClass, $surfaceClasses])
            <button type="button" wire:click="filterGroup('{{ $groupKey }}')" aria-pressed="{{ $groupFilter === $groupKey ? 'true' : 'false' }}" class="group min-h-28 rounded-2xl border p-4 text-left shadow-sm transition hover:-translate-y-1 hover:shadow-lg 2xl:min-h-40 2xl:p-5 {{ $surfaceClasses }} {{ $groupFilter === $groupKey ? 'ring-2 ring-brand-500 ring-offset-2' : '' }}">
                <div class="flex items-start justify-between gap-3 2xl:gap-4">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl text-white shadow-sm transition group-hover:scale-105 2xl:h-12 2xl:w-12 2xl:rounded-2xl {{ $dotClass }}">
                        @switch($groupKey)
                            @case('waiting')
                                <svg class="h-6 w-6 2xl:h-7 2xl:w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.1a7.5 7.5 0 0 1 10.1-6.98M18 14.25v3.75l2.25 1.5" /></svg>
                                @break
                            @case('in_service')
                                <svg class="h-6 w-6 2xl:h-7 2xl:w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6.75 9a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm0 12a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM8.9 8.9 20.25 20.25M8.9 15.1 20.25 3.75M14.25 12l1.5 1.5" /></svg>
                                @break
                            @case('pending_payment')
                                <svg class="h-6 w-6 2xl:h-7 2xl:w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.25 6.75h19.5v10.5H2.25V6.75Zm3 0a3 3 0 0 1-3 3m19.5 0a3 3 0 0 1-3-3m-13.5 10.5a3 3 0 0 0-3-3m19.5 0a3 3 0 0 0-3 3M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                                @break
                            @case('upcoming')
                                <svg class="h-6 w-6 2xl:h-7 2xl:w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6.75 3v2.25M17.25 3v2.25M3.75 9h16.5m-15 12h13.5a1.5 1.5 0 0 0 1.5-1.5V6.75a1.5 1.5 0 0 0-1.5-1.5H5.25a1.5 1.5 0 0 0-1.5 1.5V19.5a1.5 1.5 0 0 0 1.5 1.5Zm8.25-8.25h.008v.008H13.5v-.008Zm-4.5 0h.008v.008H9v-.008Zm4.5 3.75h.008v.008H13.5V16.5Zm-4.5 0h.008v.008H9V16.5Z" /></svg>
                                @break
                            @case('finished')
                                <svg class="h-6 w-6 2xl:h-7 2xl:w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12.75 11.25 15 15 9.75m6 2.25a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                @break
                        @endswitch
                    </span>
                    <strong class="text-2xl font-black leading-none 2xl:text-3xl">{{ $groupCounts->get($groupKey) }}</strong>
                </div>
                <p class="mt-3 text-base font-black leading-tight 2xl:mt-4 2xl:text-lg">{{ $label }}</p>
                <p class="mt-1 text-xs font-medium opacity-75 2xl:text-sm">{{ $subtitle }}</p>
            </button>
        @endforeach
    </div>

    @if ($isSelectedToday)
    <section class="mt-6 overflow-visible rounded-2xl border border-teal-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-teal-100 bg-teal-50/70 px-5 py-4 sm:px-6">
            <div class="flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-teal-600 text-white shadow-sm">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.1a7.5 7.5 0 0 1 15 0M18 9.75v6m3-3h-6" /></svg>
                </span>
                <div>
                    <h3 class="text-lg font-black text-slate-950">Fila sin cita</h3>
                    <p class="text-sm font-medium text-slate-600">Orden de llegada y espera estimada</p>
                </div>
            </div>
            <span class="rounded-full bg-teal-600 px-4 py-2 text-sm font-black text-white">{{ $walkInEntries->count() }} esperando</span>
        </div>

        @if ($walkInEntries->isEmpty())
            <div class="px-6 py-7 text-center text-sm font-semibold text-slate-500">No hay clientes sin cita esperando.</div>
        @else
            <div class="space-y-3 p-3 sm:p-5">
                @foreach ($walkInEntries as $entry)
                    @php
                        $eligibleBarbers = $entry->service->barbers;
                        if (auth()->user()->role === \App\Enums\UserRole::Barber) {
                            $eligibleBarbers = $eligibleBarbers->where('user_id', auth()->id());
                        }
                        $barberWaitMinutes = $entry->getAttribute('barber_wait_minutes') ?? [];
                        $busyBarberIds = $entry->getAttribute('busy_barber_ids') ?? [];
                        $selectedQueueBarberId = auth()->user()->role === \App\Enums\UserRole::Barber
                            ? auth()->user()->barberProfile?->id
                            : ($walkInBarberSelections[$entry->id] ?? $entry->preferred_barber_id);
                        $selectedQueueBarberWait = $selectedQueueBarberId && array_key_exists($selectedQueueBarberId, $barberWaitMinutes)
                            ? $barberWaitMinutes[$selectedQueueBarberId]
                            : null;
                        $selectedQueueBarberIsBusy = $selectedQueueBarberId && in_array((int) $selectedQueueBarberId, $busyBarberIds, true);
                        $selectedQueueBarberCanStart = $selectedQueueBarberId && ! $selectedQueueBarberIsBusy && $selectedQueueBarberWait === 0;
                    @endphp
                    <article wire:key="walk-in-{{ $entry->id }}" class="grid gap-4 rounded-2xl border border-teal-200 bg-teal-50/30 p-4 lg:grid-cols-[70px_minmax(190px,1.2fr)_minmax(180px,1fr)_190px_minmax(260px,auto)] lg:items-center sm:p-5">
                        <div class="flex items-center gap-3 lg:block">
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-teal-600 text-xl font-black text-white">{{ $loop->iteration }}</span>
                            <span class="text-xs font-extrabold uppercase tracking-wide text-teal-800 lg:mt-2 lg:block">Turno</span>
                        </div>

                        <div class="min-w-0">
                            <p class="truncate text-base font-black text-slate-950">{{ $entry->client->full_name }}</p>
                            <a href="tel:{{ $entry->client->phone }}" class="mt-1 inline-flex items-center gap-1.5 text-sm font-bold text-brand-700 hover:underline">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293a1.125 1.125 0 0 1-1.21.38 12.035 12.035 0 0 1-7.143-7.143 1.125 1.125 0 0 1 .38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102A1.125 1.125 0 0 0 5.872 2.25H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" /></svg>
                                {{ $entry->client->phone }}
                            </a>
                            <p class="mt-2 text-xs font-semibold text-slate-500">Llegó a las {{ $entry->arrived_at->format('H:i') }}</p>
                        </div>

                        <div>
                            <p class="text-sm font-black text-slate-900">{{ $entry->service->name }}</p>
                            <p class="mt-1 text-xs font-semibold text-slate-500">{{ $entry->service->duration_minutes }} min · ${{ number_format((float) $entry->service->price, 2) }}</p>
                            <p class="mt-2 text-xs font-bold text-teal-800">
                                {{ $entry->preferredBarber ? 'Prefiere: '.$entry->preferredBarber->display_name : 'Cualquier barbero' }}
                            </p>
                        </div>

                        <div>
                            <x-appointment-timer label="Tiempo en fila" :started-at="$entry->arrived_at" tone="cyan" icon="wait" />
                            @if ($entry->estimated_wait_minutes !== null)
                                <p class="mt-2 text-xs font-extrabold text-slate-600">Espera estimada: ~{{ $entry->estimated_wait_minutes }} min</p>
                            @else
                                <p class="mt-2 text-xs font-extrabold text-red-700">Sin disponibilidad dentro del horario de hoy</p>
                            @endif
                        </div>

                        <div class="space-y-2 lg:text-right">
                            @if ($eligibleBarbers->isNotEmpty())
                                @if (auth()->user()->role !== \App\Enums\UserRole::Barber)
                                    <select wire:model.live="walkInBarberSelections.{{ $entry->id }}" class="w-full rounded-xl border-slate-300 py-2.5 text-sm font-bold focus:border-teal-500 focus:ring-teal-500">
                                        <option value="">Asignar barbero</option>
                                        @foreach ($eligibleBarbers as $eligibleBarber)
                                            <option value="{{ $eligibleBarber->id }}" @selected($entry->preferred_barber_id === $eligibleBarber->id)>
                                                {{ $eligibleBarber->display_name }}{{ $entry->preferred_barber_id === $eligibleBarber->id ? ' · preferido' : '' }} ·
                                                @if (in_array($eligibleBarber->id, $busyBarberIds, true))
                                                    en servicio ahora
                                                @elseif (($barberWaitMinutes[$eligibleBarber->id] ?? null) === 0)
                                                    disponible ahora
                                                @elseif (($barberWaitMinutes[$eligibleBarber->id] ?? null) !== null)
                                                    disponible en ~{{ $barberWaitMinutes[$eligibleBarber->id] }} min
                                                @else
                                                    sin espacio hoy
                                                @endif
                                            </option>
                                        @endforeach
                                    </select>
                                @else
                                    <p class="text-sm font-extrabold text-slate-800">Atender con {{ auth()->user()->barberProfile?->display_name }}</p>
                                @endif

                                <button type="button" wire:click="startWalkIn({{ $entry->id }})" wire:loading.attr="disabled" wire:target="startWalkIn({{ $entry->id }})" @disabled(! $selectedQueueBarberCanStart) class="min-h-11 w-full rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-extrabold text-white shadow-sm hover:bg-violet-700 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-600 disabled:opacity-100">
                                    @if (! $selectedQueueBarberId)
                                        Selecciona un barbero
                                    @elseif ($selectedQueueBarberIsBusy)
                                        Barbero en servicio
                                    @elseif ($selectedQueueBarberWait === null)
                                        Sin disponibilidad hoy
                                    @elseif ($selectedQueueBarberWait > 0)
                                        Disponible en ~{{ $selectedQueueBarberWait }} min
                                    @else
                                        Iniciar servicio
                                    @endif
                                </button>
                            @else
                                <p class="rounded-xl bg-amber-100 px-3 py-2 text-xs font-bold text-amber-800">No realizas este servicio.</p>
                            @endif
                            <x-input-error :messages="$errors->get('walkInBarberSelections.'.$entry->id)" />

                            @can('markLeft', $entry)
                                <button type="button" wire:click="markWalkInLeft({{ $entry->id }})" wire:confirm="¿Confirmas que el cliente se retiró sin servicio?" class="text-xs font-extrabold text-red-700 hover:underline">Se retiró</button>
                            @endcan
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
    @endif

    <details class="group mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" @if (! $isSelectedToday && $leftWalkInEntries->isNotEmpty()) open @endif>
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 hover:bg-slate-50 sm:px-6">
            <span class="flex min-w-0 items-center gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-slate-700 text-white">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                </span>
                <span class="min-w-0">
                    <strong class="block text-lg font-black text-slate-950">Retirados</strong>
                    <small class="block truncate text-sm font-medium text-slate-500">Clientes sin cita que abandonaron la fila</small>
                </span>
            </span>
            <span class="flex shrink-0 items-center gap-3">
                <span class="rounded-full bg-slate-100 px-3 py-1.5 text-sm font-black text-slate-700">{{ $leftWalkInEntries->count() }}</span>
                <svg class="h-5 w-5 text-slate-400 transition group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 9 6 6 6-6" /></svg>
            </span>
        </summary>

        <div class="border-t border-slate-100">
            @forelse ($leftWalkInEntries as $entry)
                @php
                    $waitedSeconds = $entry->left_at
                        ? (int) $entry->arrived_at->diffInSeconds($entry->left_at, true)
                        : null;
                @endphp
                <article wire:key="left-walk-in-{{ $entry->id }}" class="grid gap-4 border-b border-slate-100 px-5 py-4 last:border-b-0 md:grid-cols-[minmax(180px,1.2fr)_minmax(150px,1fr)_160px_185px] md:items-center sm:px-6">
                    <div class="min-w-0">
                        <p class="truncate text-base font-black text-slate-950">{{ $entry->client->full_name }}</p>
                        <a href="tel:{{ $entry->client->phone }}" class="mt-1 inline-flex items-center gap-1.5 text-sm font-bold text-brand-700 hover:underline">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293a1.125 1.125 0 0 1-1.21.38 12.035 12.035 0 0 1-7.143-7.143 1.125 1.125 0 0 1 .38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102A1.125 1.125 0 0 0 5.872 2.25H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" /></svg>
                            {{ $entry->client->phone }}
                        </a>
                    </div>

                    <div>
                        <p class="text-sm font-black text-slate-900">{{ $entry->service->name }}</p>
                        <p class="mt-1 text-xs font-semibold text-slate-500">{{ $entry->preferredBarber ? 'Prefería: '.$entry->preferredBarber->display_name : 'Sin barbero preferido' }}</p>
                    </div>

                    <div class="text-sm">
                        <p class="font-bold text-slate-700">Llegó: {{ $entry->arrived_at->format('H:i') }}</p>
                        <p class="mt-1 font-bold text-red-700">Se retiró: {{ $entry->left_at?->format('H:i') ?? '—' }}</p>
                    </div>

                    <x-appointment-timer label="Espera antes de retirarse" :seconds="$waitedSeconds" icon="wait" />
                </article>
            @empty
                <div class="px-6 py-7 text-center text-sm font-semibold text-slate-500">No hubo clientes retirados en esta fecha.</div>
            @endforelse
        </div>
    </details>

    <div class="mt-6 space-y-5">
        @if ($visibleGroupKeys->isEmpty())
            <section class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center shadow-sm">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6.75 3v2.25M17.25 3v2.25M3.75 9h16.5m-15 12h13.5a1.5 1.5 0 0 0 1.5-1.5V6.75a1.5 1.5 0 0 0-1.5-1.5H5.25a1.5 1.5 0 0 0-1.5 1.5V19.5a1.5 1.5 0 0 0 1.5 1.5Z" /></svg>
                </div>
                <p class="mt-3 font-extrabold text-slate-800">No hay citas en esta etapa.</p>
                @if ($groupFilter !== 'all')
                    <button type="button" wire:click="filterGroup('all')" class="mt-3 text-sm font-bold text-brand-700 hover:underline">Mostrar toda la agenda</button>
                @endif
            </section>
        @endif

        @foreach ($visibleGroupKeys as $groupKey)
            @php
                [$groupTitle, $groupSubtitle, $groupColor] = $groupConfiguration[$groupKey];
                $groupAppointments = $groups->get($groupKey, collect());
            @endphp

            @if ($groupKey === 'finished')
                <details class="group overflow-visible rounded-2xl border border-slate-200 bg-white shadow-sm" @if ($groupFilter === 'finished') open @endif>
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 rounded-2xl px-5 py-5 hover:bg-emerald-50/40 sm:px-6">
                        <span class="flex min-w-0 items-center gap-4">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-500 text-white shadow-sm">
                                <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12.75 11.25 15 15 9.75m6 2.25a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                            </span>
                            <span class="min-w-0"><strong class="block text-xl font-black text-slate-950">{{ $groupTitle }}</strong><small class="mt-1 block text-sm font-medium text-slate-500">{{ $groupSubtitle }} · Contraídas para priorizar la operación</small></span>
                        </span>
                        <span class="flex shrink-0 items-center gap-3">
                            <span class="rounded-full bg-emerald-100 px-4 py-2 text-base font-black text-emerald-800">{{ $groupAppointments->count() }}</span>
                            <svg class="h-5 w-5 text-slate-400 transition-transform group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 9 6 6 6-6" /></svg>
                        </span>
                    </summary>
                    <div class="space-y-3 border-t border-slate-100 p-3 sm:p-5">
                        @foreach ($groupAppointments as $appointment)
                            @include('livewire.appointments.partials.today-appointment', ['appointment' => $appointment])
                        @endforeach
                    </div>
                </details>
            @else
                <section wire:key="group-{{ $groupKey }}" class="overflow-visible rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-4 sm:px-6">
                        <div class="flex items-center gap-3">
                            <span class="h-3 w-3 rounded-full {{ $groupColor }}"></span>
                            <div><h3 class="text-base font-extrabold text-slate-900">{{ $groupTitle }}</h3><p class="text-xs text-slate-500">{{ $groupSubtitle }}</p></div>
                        </div>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-extrabold text-slate-700">{{ $groupAppointments->count() }}</span>
                    </div>
                    <div class="space-y-3 p-3 sm:p-5">
                        @foreach ($groupAppointments as $appointment)
                            @include('livewire.appointments.partials.today-appointment', ['appointment' => $appointment])
                        @endforeach
                    </div>
                </section>
            @endif
        @endforeach
    </div>

    @if ($showWalkInModal)
        <div class="fixed inset-0 z-[70] overflow-y-auto px-4 py-6" role="dialog" aria-modal="true" aria-labelledby="walk-in-title">
            <button type="button" wire:click="closeWalkIn" class="fixed inset-0 bg-slate-950/60" aria-label="Cerrar"></button>
            <div class="relative mx-auto w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl">
                <form wire:submit="registerWalkIn">
                    <div class="border-b border-slate-200 px-5 py-4 sm:px-6">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-teal-600">Llegada sin reservación</p>
                        <h3 id="walk-in-title" class="mt-1 text-2xl font-black text-slate-950">Agregar cliente a la fila</h3>
                        <p class="mt-1 text-sm text-slate-500">El cronómetro comenzará al guardar.</p>
                    </div>

                    <div class="space-y-5 p-5 sm:p-6">
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <input type="checkbox" wire:model.live="walkInCreateClient" class="rounded border-slate-300 text-teal-600 focus:ring-teal-500">
                            <span><strong class="block text-sm text-slate-900">Es un cliente nuevo</strong><small class="text-xs text-slate-500">Crear ficha rápida con nombre y teléfono</small></span>
                        </label>

                        @if ($walkInCreateClient)
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <x-input-label for="walk-in-first-name" value="Nombre" />
                                    <x-text-input id="walk-in-first-name" wire:model="walkInFirstName" class="mt-1 block w-full" />
                                    <x-input-error :messages="$errors->get('walkInFirstName')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="walk-in-last-name" value="Apellidos" />
                                    <x-text-input id="walk-in-last-name" wire:model="walkInLastName" class="mt-1 block w-full" />
                                    <x-input-error :messages="$errors->get('walkInLastName')" class="mt-2" />
                                </div>
                                <div class="sm:col-span-2">
                                    <x-input-label for="walk-in-phone" value="Teléfono" />
                                    <x-text-input id="walk-in-phone" wire:model="walkInPhone" class="mt-1 block w-full" />
                                    <x-input-error :messages="$errors->get('walkInPhone')" class="mt-2" />
                                </div>
                            </div>
                        @else
                            <div>
                                <x-input-label for="walk-in-search" value="Buscar cliente por nombre o teléfono" />
                                <div class="relative mt-1">
                                    <x-text-input id="walk-in-search" wire:model.live.debounce.300ms="walkInClientSearch" class="block w-full pr-11" placeholder="Escribe nombre o teléfono..." autocomplete="off" />
                                    <svg wire:loading.remove wire:target="walkInClientSearch" class="pointer-events-none absolute right-3 top-3 h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m21 21-4.35-4.35m1.35-5.4a6.75 6.75 0 1 1-13.5 0 6.75 6.75 0 0 1 13.5 0Z" /></svg>
                                    <svg wire:loading wire:target="walkInClientSearch" class="absolute right-3 top-3 h-5 w-5 animate-spin text-teal-600" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path></svg>
                                </div>

                                @if ($selectedWalkInClient)
                                    <div class="mt-3 flex items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                                        <span class="min-w-0">
                                            <span class="block text-xs font-extrabold uppercase tracking-wide text-emerald-700">Cliente seleccionado</span>
                                            <strong class="mt-0.5 block truncate text-sm text-slate-950">{{ $selectedWalkInClient->full_name }} · {{ $selectedWalkInClient->phone }}</strong>
                                        </span>
                                        <button type="button" wire:click="clearWalkInClient" class="shrink-0 text-xs font-extrabold text-slate-600 hover:text-red-700">Cambiar</button>
                                    </div>
                                @elseif (filled($walkInClientSearch))
                                    <div class="mt-2 max-h-52 overflow-y-auto rounded-xl border border-slate-200 bg-white p-1 shadow-lg">
                                        @forelse ($walkInClients as $client)
                                            <button
                                                type="button"
                                                wire:click="selectWalkInClient({{ $client->id }})"
                                                @disabled($client->is_waiting_in_queue)
                                                class="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-3 text-left hover:bg-teal-50 disabled:cursor-not-allowed disabled:bg-amber-50"
                                            >
                                                <span class="min-w-0">
                                                    <strong class="block truncate text-sm text-slate-900">{{ $client->full_name }}</strong>
                                                    <span class="mt-0.5 block text-xs font-semibold text-slate-500">{{ $client->phone }}</span>
                                                </span>
                                                @if ($client->is_waiting_in_queue)
                                                    <span class="shrink-0 rounded-full bg-amber-100 px-2.5 py-1 text-[10px] font-extrabold uppercase text-amber-800">Ya está en fila</span>
                                                @else
                                                    <span class="shrink-0 text-xs font-extrabold text-teal-700">Seleccionar</span>
                                                @endif
                                            </button>
                                        @empty
                                            <div class="px-3 py-5 text-center text-sm font-semibold text-slate-500">No encontramos clientes con esa búsqueda.</div>
                                        @endforelse
                                    </div>
                                @else
                                    <p class="mt-2 text-xs font-medium text-slate-500">Escribe al menos parte del nombre o el teléfono.</p>
                                @endif
                                <x-input-error :messages="$errors->get('walkInClientId')" class="mt-2" />
                            </div>
                        @endif

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label for="walk-in-service" value="Servicio solicitado" />
                                <select id="walk-in-service" wire:model.live="walkInServiceId" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                                    <option value="">Selecciona un servicio</option>
                                    @foreach ($services as $service)
                                        <option value="{{ $service->id }}">{{ $service->name }} · {{ $service->duration_minutes }} min · ${{ number_format((float) $service->price, 2) }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('walkInServiceId')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="walk-in-preferred-barber" value="Barbero preferido (opcional)" />
                                <select id="walk-in-preferred-barber" wire:model="walkInPreferredBarberId" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                                    <option value="">Cualquier barbero</option>
                                    @foreach ($walkInPreferredBarbers as $preferredBarber)
                                        <option value="{{ $preferredBarber->id }}">{{ $preferredBarber->display_name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('walkInPreferredBarberId')" class="mt-2" />
                            </div>
                        </div>

                        <div>
                            <x-input-label for="walk-in-notes" value="Notas (opcional)" />
                            <textarea id="walk-in-notes" wire:model="walkInNotes" rows="3" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500" placeholder="Preferencias o información para recepción"></textarea>
                            <x-input-error :messages="$errors->get('walkInNotes')" class="mt-2" />
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:px-6">
                        <button type="button" wire:click="closeWalkIn" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-100">Cancelar</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="registerWalkIn" class="rounded-xl bg-teal-600 px-5 py-2.5 text-sm font-extrabold text-white hover:bg-teal-700 disabled:opacity-60">
                            <span wire:loading.remove wire:target="registerWalkIn">Agregar a la fila</span>
                            <span wire:loading wire:target="registerWalkIn">Registrando…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($showPaymentModal && $paymentAppointment)
        <div class="fixed inset-0 z-[70] overflow-y-auto px-4 py-6" role="dialog" aria-modal="true">
            <button type="button" wire:click="closePayment" class="fixed inset-0 bg-slate-950/60" aria-label="Cerrar"></button>
            <div class="relative mx-auto w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
                <form wire:submit="registerPayment">
                    <div class="border-b border-slate-200 px-5 py-4 sm:px-6">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-600">Cobro</p>
                        <h3 class="mt-1 text-xl font-bold text-slate-900">Registrar pago</h3>
                        <p class="mt-1 text-sm text-slate-500">{{ $paymentAppointment->client->full_name }} · {{ $paymentAppointment->service->name }}</p>
                    </div>

                    <div class="space-y-5 p-5 sm:p-6">
                        <x-input-error :messages="$errors->get('payment')" />
                        <div class="rounded-xl bg-slate-50 px-4 py-4">
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Total a cobrar</p>
                            <p class="mt-1 text-3xl font-extrabold text-slate-900">${{ number_format((float) $paymentAppointment->price, 2) }}</p>
                        </div>

                        <div>
                            <x-input-label for="payment-method" value="Método de pago" />
                            <select id="payment-method" wire:model="payment_method" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                @foreach ($paymentMethods as $method)
                                    <option value="{{ $method->value }}">{{ $method->label() }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('payment_method')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="payment-reference" value="Referencia (opcional)" />
                            <x-text-input id="payment-reference" wire:model="payment_reference" type="text" class="mt-1 block w-full" placeholder="Folio, autorización o nota" />
                            <x-input-error :messages="$errors->get('payment_reference')" class="mt-2" />
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:px-6">
                        <button type="button" wire:click="closePayment" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">Cancelar</button>
                        <button type="submit" wire:loading.attr="disabled" class="rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-brand-700 disabled:opacity-60">
                            <span wire:loading.remove wire:target="registerPayment">Cobrar y terminar</span>
                            <span wire:loading wire:target="registerPayment">Registrando…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <livewire:sales.receipt-delivery />
</div>
