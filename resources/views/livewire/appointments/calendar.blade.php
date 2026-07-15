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
        </div>
    @endif

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 p-4 sm:flex sm:items-center sm:justify-between sm:gap-4 sm:p-6">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Calendario</h2>
                <p class="mt-1 text-sm text-slate-500">
                    {{ $canManage ? 'Selecciona un horario para crear una cita o arrastra una existente para moverla.' : 'Consulta las citas programadas por día, semana o mes.' }}
                </p>
            </div>
            <div class="mt-4 flex flex-col gap-3 sm:mt-0 sm:flex-row sm:items-end">
                <div>
                    <label for="barber-filter" class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Barbero</label>
                    <select id="barber-filter" wire:model.live="barberFilter" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:w-52">
                        <option value="">Todos los barberos</option>
                        @foreach ($barbers as $barber)
                            <option value="{{ $barber->id }}">{{ $barber->display_name }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($canManage)
                    <button type="button" wire:click="openCreate" class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-brand-700">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        Nueva cita
                    </button>
                @endif
            </div>
        </div>

        <div class="flex flex-wrap gap-x-5 gap-y-2 border-b border-slate-100 bg-slate-50/80 px-4 py-3 sm:px-6">
            @foreach ($barbers as $barber)
                <span class="inline-flex items-center gap-2 text-xs font-semibold text-slate-600">
                    <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $barber->calendarColor() }}"></span>
                    {{ $barber->display_name }}
                </span>
            @endforeach
        </div>

        <div class="p-3 sm:p-6">
            <div wire:ignore id="appointment-calendar" data-feed-url="{{ route('appointments.feed') }}"></div>
        </div>
    </section>

    @if ($showModal)
        <div class="fixed inset-0 z-[60] overflow-y-auto px-4 py-6" role="dialog" aria-modal="true">
            <button type="button" wire:click="closeModal" class="fixed inset-0 bg-slate-950/60" aria-label="Cerrar"></button>
            <div class="relative mx-auto w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-2xl">
                <form wire:submit="save">
                    <div class="flex items-start justify-between border-b border-slate-200 px-5 py-4 sm:px-6">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-600">Agenda</p>
                            <h3 class="mt-1 text-xl font-bold text-slate-900">{{ $form->appointment ? 'Editar cita' : 'Nueva cita' }}</h3>
                        </div>
                        <button type="button" wire:click="closeModal" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" aria-label="Cerrar">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 18 18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <div class="grid gap-5 p-5 sm:grid-cols-2 sm:p-6">
                        <div class="sm:col-span-2">
                            <x-input-label for="appointment-client" value="Cliente" />
                            <select id="appointment-client" wire:model="form.client_id" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                <option value="">Selecciona un cliente</option>
                                @foreach ($clients as $client)
                                    <option value="{{ $client->id }}">{{ $client->full_name }} · {{ $client->phone }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('form.client_id')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="appointment-barber" value="Barbero" />
                            <select id="appointment-barber" wire:model.live="form.barber_id" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                <option value="">Selecciona un barbero</option>
                                @foreach ($barbers as $barber)
                                    <option value="{{ $barber->id }}">{{ $barber->display_name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('form.barber_id')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="appointment-service" value="Servicio" />
                            <select id="appointment-service" wire:model.live="form.service_id" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                <option value="">Selecciona un servicio</option>
                                @foreach ($services as $service)
                                    <option value="{{ $service->id }}">{{ $service->name }} · {{ $service->duration_minutes }} min</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('form.service_id')" class="mt-2" />
                            @if ($form->barber_id && $services->isEmpty())
                                <p class="mt-2 text-xs font-medium text-amber-700">Este barbero no tiene servicios activos asignados.</p>
                            @endif
                        </div>

                        <div>
                            <x-input-label for="appointment-start" value="Fecha y hora de inicio" />
                            <x-text-input id="appointment-start" wire:model="form.starts_at" type="datetime-local" step="300" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('form.starts_at')" class="mt-2" />
                            <p class="mt-1 text-xs text-slate-500">El término se calcula con la duración del servicio.</p>
                        </div>

                        <div>
                            <x-input-label for="appointment-price" value="Precio" />
                            <div class="relative mt-1">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-500">$</span>
                                <x-text-input id="appointment-price" wire:model="form.price" type="number" min="0" step="0.01" class="block w-full pl-7" />
                            </div>
                            <x-input-error :messages="$errors->get('form.price')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="appointment-status" value="Estado" />
                            <select id="appointment-status" wire:model="form.status" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                @foreach ($statuses as $status)
                                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('form.status')" class="mt-2" />
                        </div>

                        <div class="sm:col-span-2">
                            <x-input-label for="appointment-notes" value="Notas" />
                            <textarea id="appointment-notes" wire:model="form.notes" rows="3" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" placeholder="Indicaciones o comentarios de la cita"></textarea>
                            <x-input-error :messages="$errors->get('form.notes')" class="mt-2" />
                        </div>

                        @if ($form->appointment)
                            <div class="sm:col-span-2 rounded-xl bg-slate-50 px-4 py-3 text-xs text-slate-600">
                                Creada por <strong>{{ $form->appointment->creator?->name ?? 'Usuario eliminado' }}</strong>
                                · Última modificación por <strong>{{ $form->appointment->updater?->name ?? 'Usuario eliminado' }}</strong>
                            </div>
                        @endif
                    </div>

                    <div class="flex justify-end gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:px-6">
                        <button type="button" wire:click="closeModal" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">Cancelar</button>
                        <button type="submit" wire:loading.attr="disabled" class="rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-brand-700 disabled:opacity-60">
                            <span wire:loading.remove wire:target="save">Guardar cita</span>
                            <span wire:loading wire:target="save">Guardando…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

@script
<script>
    const element = document.getElementById('appointment-calendar')

    if (element && window.FullCalendar) {
        const canManage = @js($canManage)
        const calendar = new FullCalendar.Calendar(element, {
            locale: 'es',
            initialView: window.innerWidth < 768 ? 'listWeek' : 'timeGridWeek',
            firstDay: 1,
            nowIndicator: true,
            allDaySlot: false,
            selectable: canManage,
            editable: canManage,
            eventDurationEditable: false,
            slotMinTime: '07:00:00',
            slotMaxTime: '22:00:00',
            slotDuration: '00:30:00',
            height: 'auto',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
            },
            buttonText: {
                today: 'Hoy',
                month: 'Mes',
                week: 'Semana',
                day: 'Día',
                list: 'Lista'
            },
            events: {
                url: element.dataset.feedUrl,
                extraParams: () => ({ barber_id: $wire.barberFilter || '' })
            },
            select: info => {
                if (canManage) {
                    $wire.openCreate(info.startStr)
                    calendar.unselect()
                }
            },
            eventClick: info => {
                if (canManage) {
                    $wire.openEdit(Number(info.event.id))
                }
            },
            eventDrop: async info => {
                const result = await $wire.moveAppointment(Number(info.event.id), info.event.start.toISOString())

                if (!result.ok) {
                    info.revert()
                    window.alert(result.message)
                    return
                }

                calendar.refetchEvents()
            },
            eventDidMount: info => {
                info.el.title = `${info.event.extendedProps.barber} · ${info.event.extendedProps.statusLabel}`
            }
        })

        calendar.render()
        $wire.on('appointments-changed', () => calendar.refetchEvents())
        $wire.on('barber-filter-changed', () => calendar.refetchEvents())
    }
</script>
@endscript
