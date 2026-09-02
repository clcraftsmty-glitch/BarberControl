<div class="mx-auto max-w-6xl space-y-6">
    <header>
        <p class="text-xs font-black uppercase tracking-[0.18em] text-brand-600">Administración</p>
        <h1 class="mt-1 text-2xl font-black text-slate-950">Configuración de la barbería</h1>
        <p class="mt-1 text-sm text-slate-500">Centraliza la identidad, operación, impuestos y reglas de agenda.</p>
    </header>

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('status') }}</div>
    @endif

    <form wire:submit="save" class="space-y-6">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            <div class="mb-5"><h2 class="text-lg font-black text-slate-950">Identidad y contacto</h2><p class="text-sm text-slate-500">Estos datos identifican el negocio y pueden mostrarse en los tickets.</p></div>
            <div class="grid gap-5 lg:grid-cols-[220px_minmax(0,1fr)]">
                <div>
                    <div class="flex h-36 items-center justify-center overflow-hidden rounded-2xl border border-dashed border-slate-300 bg-slate-50">
                        @if ($logo)
                            <img src="{{ $logo->temporaryUrl() }}" alt="Vista previa" class="h-full w-full object-contain p-3">
                        @elseif ($settings->logoUrl())
                            <img src="{{ $settings->logoUrl() }}" alt="Logotipo actual" class="h-full w-full object-contain p-3">
                        @else
                            <div class="text-center text-slate-400"><svg class="mx-auto h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M3 16.5V7.5A2.5 2.5 0 0 1 5.5 5h13A2.5 2.5 0 0 1 21 7.5v9a2.5 2.5 0 0 1-2.5 2.5h-13A2.5 2.5 0 0 1 3 16.5Zm3-1.5 3.25-3.25 2.5 2.5L15.5 10l2.5 3" /></svg><span class="mt-2 block text-xs font-bold">Sin logotipo</span></div>
                        @endif
                    </div>
                    <label class="mt-3 block cursor-pointer rounded-xl border border-slate-300 px-3 py-2 text-center text-xs font-bold text-slate-700 hover:bg-slate-50">Seleccionar imagen<input type="file" wire:model="logo" accept="image/png,image/jpeg,image/webp" class="sr-only"></label>
                    @if ($settings->logo_path)<button type="button" wire:click="removeLogo" wire:confirm="¿Eliminar el logotipo actual?" class="mt-2 w-full text-xs font-bold text-red-600">Eliminar logotipo</button>@endif
                    <x-input-error :messages="$errors->get('logo')" class="mt-2" />
                    <p class="mt-2 text-center text-[11px] text-slate-400">PNG, JPG o WEBP. Máximo 2 MB.</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><x-input-label for="business-name" value="Nombre comercial" /><x-text-input id="business-name" wire:model="business_name" class="mt-1 block w-full" /><x-input-error :messages="$errors->get('business_name')" class="mt-2" /></div>
                    <div><x-input-label for="legal-name" value="Razón social (opcional)" /><x-text-input id="legal-name" wire:model="legal_name" class="mt-1 block w-full" /><x-input-error :messages="$errors->get('legal_name')" class="mt-2" /></div>
                    <div><x-input-label for="tax-id" value="RFC / identificación fiscal (opcional)" /><x-text-input id="tax-id" wire:model="tax_id" class="mt-1 block w-full uppercase" /><x-input-error :messages="$errors->get('tax_id')" class="mt-2" /></div>
                    <div class="sm:col-span-2"><x-input-label for="address" value="Dirección" /><textarea id="address" wire:model="address" rows="3" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500"></textarea><x-input-error :messages="$errors->get('address')" class="mt-2" /></div>
                    <div class="sm:col-span-2">
                        <div class="flex items-center justify-between"><x-input-label value="Teléfonos" /><button type="button" wire:click="addPhone" class="text-xs font-black text-brand-600">+ Agregar teléfono</button></div>
                        <div class="mt-2 grid gap-2 sm:grid-cols-2">@foreach($phones as $index => $phone)<div class="flex gap-2" wire:key="business-phone-{{ $index }}"><x-text-input type="tel" wire:model="phones.{{ $index }}" placeholder="Teléfono {{ $index + 1 }}" class="block min-w-0 flex-1" /><button type="button" wire:click="removePhone({{ $index }})" class="rounded-xl border border-red-200 px-3 text-red-600" aria-label="Quitar teléfono">×</button></div>@endforeach</div>
                        <x-input-error :messages="$errors->get('phones.*')" class="mt-2" />
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            <div class="mb-5"><h2 class="text-lg font-black text-slate-950">Horario general</h2><p class="text-sm text-slate-500">Una cita deberá respetar este horario y el horario individual del barbero.</p></div>
            <div class="divide-y divide-slate-100 rounded-xl border border-slate-200">@foreach($days as $day => $label)<div class="grid items-center gap-3 p-3 sm:grid-cols-[150px_1fr_1fr]" wire:key="schedule-{{ $day }}"><label class="flex items-center gap-2 text-sm font-bold text-slate-800"><input type="checkbox" wire:model="general_schedule.{{ $day }}.enabled" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">{{ $label }}</label><div><span class="mb-1 block text-[10px] font-bold uppercase text-slate-400">Apertura</span><input type="time" wire:model="general_schedule.{{ $day }}.start" @disabled(!$general_schedule[$day]['enabled']) class="block w-full rounded-xl border-slate-300 text-sm disabled:bg-slate-100"></div><div><span class="mb-1 block text-[10px] font-bold uppercase text-slate-400">Cierre</span><input type="time" wire:model="general_schedule.{{ $day }}.end" @disabled(!$general_schedule[$day]['enabled']) class="block w-full rounded-xl border-slate-300 text-sm disabled:bg-slate-100"><x-input-error :messages="$errors->get('general_schedule.'.$day.'.end')" class="mt-1" /></div></div>@endforeach</div>
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                <div class="mb-5"><h2 class="text-lg font-black text-slate-950">Moneda e impuestos</h2><p class="text-sm text-slate-500">Información fiscal y formato monetario del negocio.</p></div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><x-input-label for="currency" value="Moneda" /><select id="currency" wire:model.live="currency_code" class="mt-1 block w-full rounded-xl border-slate-300">@foreach($currencies as $code => $currency)<option value="{{ $code }}">{{ $code }} · {{ $currency['name'] }}</option>@endforeach</select><x-input-error :messages="$errors->get('currency_code')" class="mt-2" /></div>
                    <div><x-input-label for="currency-symbol" value="Símbolo" /><x-text-input id="currency-symbol" wire:model="currency_symbol" class="mt-1 block w-full" /><x-input-error :messages="$errors->get('currency_symbol')" class="mt-2" /></div>
                    <div><x-input-label for="tax-name" value="Nombre del impuesto" /><x-text-input id="tax-name" wire:model="tax_name" class="mt-1 block w-full" /><x-input-error :messages="$errors->get('tax_name')" class="mt-2" /></div>
                    <div><x-input-label for="tax-rate" value="Porcentaje" /><div class="relative mt-1"><x-text-input id="tax-rate" type="number" min="0" max="100" step="0.01" wire:model="tax_rate" class="block w-full pr-9" /><span class="absolute right-3 top-2.5 text-sm font-bold text-slate-400">%</span></div><x-input-error :messages="$errors->get('tax_rate')" class="mt-2" /></div>
                    <label class="flex gap-3 rounded-xl bg-slate-50 p-4 sm:col-span-2"><input type="checkbox" wire:model="prices_include_tax" class="mt-0.5 rounded border-slate-300 text-brand-600"><span><strong class="block text-sm text-slate-900">Los precios ya incluyen impuestos</strong><small class="text-slate-500">Permite mostrar el desglose incluido sin modificar el total cobrado.</small></span></label>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                <div class="mb-5"><h2 class="text-lg font-black text-slate-950">Datos del ticket</h2><p class="text-sm text-slate-500">Textos que acompañan los datos comerciales en cada comprobante.</p></div>
                <div class="space-y-4"><div><x-input-label for="ticket-header" value="Encabezado" /><x-text-input id="ticket-header" wire:model="ticket_header" class="mt-1 block w-full" /><x-input-error :messages="$errors->get('ticket_header')" class="mt-2" /></div><div><x-input-label for="ticket-footer" value="Mensaje al pie" /><textarea id="ticket-footer" wire:model="ticket_footer" rows="4" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500"></textarea><x-input-error :messages="$errors->get('ticket_footer')" class="mt-2" /></div></div>
            </section>
        </div>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            <div class="mb-5"><h2 class="text-lg font-black text-slate-950">Agenda y zona horaria</h2><p class="text-sm text-slate-500">Reglas predeterminadas para programar y controlar la puntualidad.</p></div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div class="sm:col-span-2 lg:col-span-1"><x-input-label for="timezone" value="Zona horaria" /><select id="timezone" wire:model="timezone" class="mt-1 block w-full rounded-xl border-slate-300 text-sm">@foreach($timezones as $zone)<option value="{{ $zone }}">{{ $zone }}</option>@endforeach</select><x-input-error :messages="$errors->get('timezone')" class="mt-2" /></div>
                <div><x-input-label for="default-duration" value="Duración predeterminada (min)" /><x-text-input id="default-duration" type="number" min="5" max="480" wire:model="default_appointment_duration_minutes" class="mt-1 block w-full" /><x-input-error :messages="$errors->get('default_appointment_duration_minutes')" class="mt-2" /></div>
                <div><x-input-label for="tolerance" value="Tolerancia de llegada (min)" /><x-text-input id="tolerance" type="number" min="0" max="240" wire:model="arrival_tolerance_minutes" class="mt-1 block w-full" /><x-input-error :messages="$errors->get('arrival_tolerance_minutes')" class="mt-2" /></div>
                <div><x-input-label for="cancellation" value="Cancelación anticipada (horas)" /><x-text-input id="cancellation" type="number" min="0" max="720" wire:model="cancellation_notice_hours" class="mt-1 block w-full" /><x-input-error :messages="$errors->get('cancellation_notice_hours')" class="mt-2" /></div>
                <div><x-input-label for="minimum-notice" value="Anticipación mínima (min)" /><x-text-input id="minimum-notice" type="number" min="0" max="43200" wire:model="minimum_booking_notice_minutes" class="mt-1 block w-full" /><x-input-error :messages="$errors->get('minimum_booking_notice_minutes')" class="mt-2" /></div>
                <div><x-input-label for="maximum-advance" value="Anticipación máxima (días)" /><x-text-input id="maximum-advance" type="number" min="1" max="3650" wire:model="maximum_booking_advance_days" class="mt-1 block w-full" /><x-input-error :messages="$errors->get('maximum_booking_advance_days')" class="mt-2" /></div>
            </div>
        </section>

        <div class="sticky bottom-4 z-20 flex justify-end rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-xl backdrop-blur"><button type="submit" wire:loading.attr="disabled" class="rounded-xl bg-brand-600 px-6 py-3 text-sm font-black text-white shadow-lg shadow-brand-600/20 disabled:opacity-60"><span wire:loading.remove wire:target="save">Guardar configuración</span><span wire:loading wire:target="save">Guardando...</span></button></div>
    </form>
</div>
