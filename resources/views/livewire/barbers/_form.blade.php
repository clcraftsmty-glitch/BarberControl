<div class="space-y-7">
    @if ($creating)
        <section class="rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:p-5">
            <h2 class="text-base font-bold text-slate-900">Cuenta de acceso</h2>
            <p class="mt-1 text-sm text-slate-500">Crea la cuenta con la que el barbero iniciará sesión o vincula una existente.</p>

            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <label class="flex cursor-pointer gap-3 rounded-xl border bg-white p-4 {{ $form->user_mode === 'new' ? 'border-brand-500 ring-1 ring-brand-500' : 'border-slate-200' }}">
                    <input type="radio" wire:model.live="form.user_mode" value="new" class="mt-0.5 border-slate-300 text-brand-600 focus:ring-brand-500">
                    <span>
                        <span class="block text-sm font-bold text-slate-800">Crear cuenta nueva</span>
                        <span class="mt-0.5 block text-xs text-slate-500">Registra correo y contraseña ahora.</span>
                    </span>
                </label>
                <label class="flex cursor-pointer gap-3 rounded-xl border bg-white p-4 {{ $form->user_mode === 'existing' ? 'border-brand-500 ring-1 ring-brand-500' : 'border-slate-200' }}">
                    <input type="radio" wire:model.live="form.user_mode" value="existing" class="mt-0.5 border-slate-300 text-brand-600 focus:ring-brand-500">
                    <span>
                        <span class="block text-sm font-bold text-slate-800">Usar cuenta existente</span>
                        <span class="mt-0.5 block text-xs text-slate-500">Solo aparecen usuarios barbero sin perfil.</span>
                    </span>
                </label>
            </div>
            @error('form.user_mode')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror

            @if ($form->user_mode === 'new')
                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="text-sm font-semibold text-slate-700">Nombre de la cuenta <span class="text-brand-600">*</span></label>
                        <input type="text" wire:model="form.user_name" autocomplete="name" maxlength="255" class="mt-2 block w-full rounded-xl border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                        @error('form.user_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-slate-700">Correo para iniciar sesión <span class="text-brand-600">*</span></label>
                        <input type="email" wire:model="form.user_email" autocomplete="username" maxlength="255" class="mt-2 block w-full rounded-xl border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                        @error('form.user_email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-slate-700">Contraseña inicial <span class="text-brand-600">*</span></label>
                        <input type="password" wire:model="form.user_password" autocomplete="new-password" class="mt-2 block w-full rounded-xl border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                        @error('form.user_password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-slate-700">Confirmar contraseña <span class="text-brand-600">*</span></label>
                        <input type="password" wire:model="form.user_password_confirmation" autocomplete="new-password" class="mt-2 block w-full rounded-xl border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                </div>
            @else
                <div class="mt-5">
                    <label class="text-sm font-semibold text-slate-700">Usuario relacionado <span class="text-brand-600">*</span></label>
                    <select wire:model="form.user_id" class="mt-2 block w-full rounded-xl border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">Selecciona un usuario barbero</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} — {{ $user->email }}</option>
                        @endforeach
                    </select>
                    @if ($users->isEmpty())
                        <p class="mt-2 text-sm text-amber-700">No hay cuentas disponibles. Selecciona “Crear cuenta nueva”.</p>
                    @endif
                    @error('form.user_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            @endif
        </section>
    @else
        <div>
            <label class="text-sm font-semibold text-slate-700">Usuario relacionado <span class="text-brand-600">*</span></label>
            <select wire:model="form.user_id" class="mt-2 block w-full rounded-xl border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="">Selecciona un usuario barbero</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}">{{ $user->name }} — {{ $user->email }}</option>
                @endforeach
            </select>
            @error('form.user_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    @endif

    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <label class="text-sm font-semibold text-slate-700">Nombre visible <span class="text-brand-600">*</span></label>
            <input type="text" wire:model="form.display_name" maxlength="150" class="mt-2 block w-full rounded-xl border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
            @error('form.display_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="text-sm font-semibold text-slate-700">Teléfono <span class="text-brand-600">*</span></label>
            <input type="tel" wire:model="form.phone" maxlength="25" class="mt-2 block w-full rounded-xl border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
            @error('form.phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="text-sm font-semibold text-slate-700">Comisión predeterminada para el barbero <span class="text-brand-600">*</span></label>
            <div class="relative mt-2">
                <input type="number" wire:model="form.default_commission_percentage" min="0" max="100" step="0.01" class="block w-full rounded-xl border-slate-300 pr-8 text-sm focus:border-brand-500 focus:ring-brand-500">
                <span class="absolute right-3 top-2.5 text-sm text-slate-500">%</span>
            </div>
            @error('form.default_commission_percentage')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="flex items-end">
            <label class="flex w-full gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <input type="checkbox" wire:model="form.is_active" class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                <span>
                    <span class="block text-sm font-semibold text-slate-800">Barbero activo</span>
                    <span class="block text-xs text-slate-500">Disponible para operar.</span>
                </span>
            </label>
        </div>
    </div>

    <section>
        <h2 class="text-base font-bold text-slate-900">Servicios que puede realizar</h2>
        <p class="mt-1 text-sm text-slate-500">Selecciona al menos un servicio.</p>
        <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($services as $service)
                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 p-3 hover:bg-slate-50">
                    <input type="checkbox" wire:model="form.service_ids" value="{{ $service->id }}" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    <span class="text-sm font-medium text-slate-700">{{ $service->name }}</span>
                </label>
            @endforeach
        </div>
        @error('form.service_ids')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        @error('form.service_ids.*')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
    </section>

    <section>
        <h2 class="text-base font-bold text-slate-900">Horario laboral</h2>
        <p class="mt-1 text-sm text-slate-500">Activa los días laborables y define su horario.</p>
        <div class="mt-3 divide-y divide-slate-100 rounded-xl border border-slate-200">
            @foreach (App\Models\Barber::DAYS as $day => $label)
                <div class="grid items-center gap-3 p-3 sm:grid-cols-[130px_1fr_1fr]">
                    <label class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <input type="checkbox" wire:model.live="form.work_schedule.{{ $day }}.enabled" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        {{ $label }}
                    </label>
                    <div>
                        <input type="time" wire:model="form.work_schedule.{{ $day }}.start" @disabled(! ($form->work_schedule[$day]['enabled'] ?? false)) class="block w-full rounded-lg border-slate-300 text-sm disabled:bg-slate-100">
                        @error("form.work_schedule.{$day}.start")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <input type="time" wire:model="form.work_schedule.{{ $day }}.end" @disabled(! ($form->work_schedule[$day]['enabled'] ?? false)) class="block w-full rounded-lg border-slate-300 text-sm disabled:bg-slate-100">
                        @error("form.work_schedule.{$day}.end")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            @endforeach
        </div>
        @error('form.work_schedule')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
    </section>
</div>
