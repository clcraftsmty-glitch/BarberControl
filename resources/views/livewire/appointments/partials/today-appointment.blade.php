@php
    $now = now();
    $isUpcoming = in_array($appointment->status, [
        \App\Enums\AppointmentStatus::Pending,
        \App\Enums\AppointmentStatus::Confirmed,
    ], true);
    $isOverdue = $isUpcoming && $appointment->starts_at->isPast();
    $isSoon = $isUpcoming && $appointment->starts_at->between($now, $now->copy()->addMinutes(15));
    $scheduledDelayMinutes = $appointment->starts_at->isPast()
        ? (int) $appointment->starts_at->diffInMinutes($now)
        : 0;
    $waitingSeconds = $appointment->waitingDurationSeconds();
    $serviceSeconds = $appointment->serviceDurationSeconds();
    $totalSeconds = $appointment->totalDurationSeconds();
    $cardClasses = match (true) {
        $appointment->status === \App\Enums\AppointmentStatus::Arrived => 'border-cyan-300 bg-cyan-50/40',
        $appointment->status === \App\Enums\AppointmentStatus::InService => 'border-violet-300 bg-violet-50/40',
        $appointment->status === \App\Enums\AppointmentStatus::PendingPayment => 'border-orange-300 bg-orange-50/50',
        $isOverdue => 'border-red-300 bg-red-50/50',
        $isSoon => 'border-amber-300 bg-amber-50/50',
        $appointment->status->isFinal() => 'border-slate-200 bg-white',
        default => 'border-blue-200 bg-white',
    };
    $canUsePrimary = $appointment->status === \App\Enums\AppointmentStatus::PendingPayment
        ? auth()->user()->can('registerPayment', $appointment)
        : auth()->user()->can('transition', $appointment);
    $primaryButtonClasses = match ($appointment->status) {
        \App\Enums\AppointmentStatus::Confirmed => 'bg-cyan-600 hover:bg-cyan-700 focus:ring-cyan-500',
        \App\Enums\AppointmentStatus::Arrived => 'bg-violet-600 hover:bg-violet-700 focus:ring-violet-500',
        \App\Enums\AppointmentStatus::InService,
        \App\Enums\AppointmentStatus::PendingPayment => 'bg-orange-600 hover:bg-orange-700 focus:ring-orange-500',
        default => 'bg-brand-600 hover:bg-brand-700 focus:ring-brand-500',
    };
@endphp

<article wire:key="appointment-{{ $appointment->id }}" class="relative overflow-visible rounded-2xl border {{ $cardClasses }} shadow-sm transition hover:shadow-md">
    <div class="grid gap-4 p-4 lg:grid-cols-[190px_minmax(220px,1.35fr)_minmax(180px,1fr)_150px_minmax(240px,auto)] lg:items-center sm:p-5">
        <div class="flex flex-wrap items-start gap-3 lg:block">
            <div class="flex h-14 min-w-20 flex-col items-center justify-center rounded-xl bg-slate-900 px-3 text-white shadow-sm lg:h-auto lg:min-w-0 lg:items-start lg:bg-transparent lg:px-0 lg:text-slate-900 lg:shadow-none">
                <p class="text-xl font-black leading-none lg:text-2xl">{{ $appointment->starts_at->format('H:i') }}</p>
                <p class="mt-1 text-[11px] font-semibold text-slate-300 lg:text-slate-500">a {{ $appointment->ends_at->format('H:i') }}</p>
            </div>

            @if ($isOverdue)
                <span class="inline-flex rounded-full bg-red-100 px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-wide text-red-700">Retrasada {{ $scheduledDelayMinutes }} min</span>
            @elseif ($isSoon)
                <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-wide text-amber-800">En {{ max(0, (int) $now->diffInMinutes($appointment->starts_at)) }} min</span>
            @endif

            <div class="flex flex-wrap gap-2 lg:block">
                @if ($appointment->status === \App\Enums\AppointmentStatus::Arrived)
                    <x-appointment-timer label="Tiempo de espera" :started-at="$appointment->arrived_at" tone="cyan" icon="wait" />
                @elseif ($appointment->status === \App\Enums\AppointmentStatus::InService)
                    <x-appointment-timer label="Tiempo de espera" :seconds="$waitingSeconds" tone="cyan" icon="wait" />
                    <x-appointment-timer label="Tiempo de servicio" :started-at="$appointment->service_started_at" tone="violet" />
                    <x-appointment-timer label="Tiempo total" :started-at="$appointment->arrived_at" />
                @elseif ($appointment->status === \App\Enums\AppointmentStatus::PendingPayment || $appointment->status->isFinal())
                    @if ($waitingSeconds !== null)
                        <x-appointment-timer label="Tiempo de espera" :seconds="$waitingSeconds" tone="cyan" icon="wait" />
                    @endif
                    @if ($serviceSeconds !== null)
                        <x-appointment-timer label="Tiempo de servicio" :seconds="$serviceSeconds" tone="violet" />
                    @endif
                    @if ($totalSeconds !== null)
                        <x-appointment-timer label="Tiempo total" :seconds="$totalSeconds" />
                    @endif
                @endif
            </div>
        </div>

        <div class="min-w-0">
            <p class="truncate text-base font-extrabold text-slate-950">{{ $appointment->client->full_name }}</p>
            <a href="tel:{{ $appointment->client->phone }}" class="mt-1.5 inline-flex items-center gap-1.5 rounded-lg bg-white px-2.5 py-1.5 text-xs font-bold text-brand-700 ring-1 ring-slate-200 hover:bg-brand-50 hover:ring-brand-200">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293a1.125 1.125 0 0 1-1.21.38 12.035 12.035 0 0 1-7.143-7.143 1.125 1.125 0 0 1 .38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102A1.125 1.125 0 0 0 5.872 2.25H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" /></svg>
                {{ $appointment->client->phone }}
            </a>
        </div>

        <div class="min-w-0">
            <p class="truncate text-sm font-bold text-slate-900">{{ $appointment->service->name }}</p>
            <p class="mt-1 flex items-center gap-1.5 truncate text-xs font-semibold text-slate-500">
                <span class="h-2 w-2 rounded-full" style="background-color: {{ $appointment->barber->calendarColor() }}"></span>
                {{ $appointment->barber->display_name }}
            </p>
        </div>

        <div>
            <p class="text-lg font-black text-slate-950">${{ number_format((float) $appointment->price, 2) }}</p>
            <span class="mt-1.5 inline-flex rounded-full px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-wide {{ $appointment->status->badgeClasses() }}">
                {{ $appointment->status->label() }}
            </span>
            @if ($appointment->source === \App\Enums\AppointmentSource::WalkIn)
                <span class="mt-1.5 inline-flex rounded-full bg-teal-100 px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-wide text-teal-800">Sin cita</span>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-2 lg:justify-end">
            @if (! $isSelectedToday)
                <span class="inline-flex items-center gap-1.5 rounded-xl bg-slate-100 px-3 py-2 text-xs font-extrabold text-slate-600">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16.5 10.5V6.75a4.5 4.5 0 0 0-9 0v3.75m-.75 10.5h10.5a2.25 2.25 0 0 0 2.25-2.25v-6A2.25 2.25 0 0 0 17.25 10.5H6.75a2.25 2.25 0 0 0-2.25 2.25v6A2.25 2.25 0 0 0 6.75 21Z" /></svg>
                    Solo lectura
                </span>
            @else
            @if (
                $appointment->status === \App\Enums\AppointmentStatus::PendingPayment
                && $canUsePrimary
                && ! $hasOpenCashRegister
            )
                <div class="flex flex-col items-start gap-1 lg:items-end">
                    <span class="inline-flex items-center gap-1.5 text-xs font-extrabold text-red-700">
                        <span class="h-2 w-2 rounded-full bg-red-500"></span>
                        Caja cerrada
                    </span>
                    <a
                        href="{{ route('cash-register.index') }}"
                        wire:navigate
                        class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-extrabold text-white shadow-sm hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125V15.75m0 0H21a.75.75 0 0 0-.75.75v.75m1.5-1.5h-18m0 0H3a.75.75 0 0 1-.75-.75m1.5.75v.75c0 .414-.336.75-.75.75h-.75m16-6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H21v-.008Zm-18 0h.008v.008H3v-.008Z" /></svg>
                        Abrir caja para cobrar
                    </a>
                </div>
            @elseif ($appointment->status->actionLabel() && $canUsePrimary)
                <button
                    type="button"
                    wire:click="advance({{ $appointment->id }})"
                    wire:loading.attr="disabled"
                    wire:target="advance({{ $appointment->id }})"
                    class="min-h-11 rounded-xl px-4 py-2.5 text-sm font-extrabold text-white shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-60 {{ $primaryButtonClasses }}"
                >
                    {{ $appointment->status->actionLabel() }}
                </button>
            @elseif ($appointment->status === \App\Enums\AppointmentStatus::PendingPayment)
                <span class="rounded-xl bg-orange-100 px-3 py-2 text-xs font-bold text-orange-800">Esperando cobro en recepción</span>
            @endif

            @can('manageException', $appointment)
                @if (! $appointment->status->isFinal())
                    <details class="relative" x-data @click.outside="$el.removeAttribute('open')">
                        <summary class="list-none cursor-pointer rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50">Más</summary>
                        <div class="absolute right-0 z-30 mt-2 w-48 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-xl">
                            <button type="button" wire:click="exceptionalTransition({{ $appointment->id }}, 'cancelada')" class="block w-full px-3 py-2.5 text-left text-xs font-semibold text-slate-700 hover:bg-slate-50">Cancelar cita</button>
                            <button type="button" wire:click="exceptionalTransition({{ $appointment->id }}, 'no_asistio')" class="block w-full px-3 py-2.5 text-left text-xs font-semibold text-red-700 hover:bg-red-50">No asistió</button>
                            <button type="button" wire:click="exceptionalTransition({{ $appointment->id }}, 'reprogramada')" class="block w-full px-3 py-2.5 text-left text-xs font-semibold text-fuchsia-700 hover:bg-fuchsia-50">Marcar reprogramada</button>
                        </div>
                    </details>
                @endif
            @endcan

            @can('updateStatus', $appointment)
                <details class="relative" x-data @click.outside="$el.removeAttribute('open')">
                    <summary class="list-none cursor-pointer rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50" title="Acción administrativa">Admin</summary>
                    <div class="absolute right-0 z-30 mt-2 w-60 rounded-xl border border-slate-200 bg-white p-3 shadow-xl">
                        <label for="admin-status-{{ $appointment->id }}" class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-slate-500">Forzar estado</label>
                        <select id="admin-status-{{ $appointment->id }}" wire:change="forceStatus({{ $appointment->id }}, $event.target.value)" class="w-full rounded-lg border-slate-300 py-2 text-xs font-bold focus:border-brand-500 focus:ring-brand-500">
                            <option value="{{ $appointment->status->value }}">{{ $appointment->status->label() }}</option>
                            @foreach ($statuses as $status)
                                @if ($status !== $appointment->status)
                                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                                @endif
                            @endforeach
                        </select>
                        <p class="mt-2 text-[10px] leading-4 text-amber-700">Uso excepcional. Terminar requiere registrar el pago.</p>
                    </div>
                </details>
            @endcan
            @endif
        </div>
    </div>
</article>
