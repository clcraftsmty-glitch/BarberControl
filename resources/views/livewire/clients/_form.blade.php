<div class="grid gap-5 sm:grid-cols-2">
    <div>
        <label for="first_name" class="text-sm font-semibold text-slate-700">Nombre <span class="text-brand-600">*</span></label>
        <input id="first_name" type="text" wire:model="form.first_name" maxlength="100" autocomplete="given-name" class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
        @error('form.first_name') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="last_name" class="text-sm font-semibold text-slate-700">Apellidos <span class="text-brand-600">*</span></label>
        <input id="last_name" type="text" wire:model="form.last_name" maxlength="100" autocomplete="family-name" class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
        @error('form.last_name') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="phone" class="text-sm font-semibold text-slate-700">Teléfono <span class="text-brand-600">*</span></label>
        <input id="phone" type="tel" wire:model="form.phone" maxlength="25" autocomplete="tel" placeholder="55 1234 5678" class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
        @error('form.phone') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="email" class="text-sm font-semibold text-slate-700">Correo electrónico <span class="font-normal text-slate-400">(opcional)</span></label>
        <input id="email" type="email" wire:model="form.email" maxlength="255" autocomplete="email" class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
        @error('form.email') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="birth_date" class="text-sm font-semibold text-slate-700">Fecha de nacimiento <span class="font-normal text-slate-400">(opcional)</span></label>
        <input id="birth_date" type="date" wire:model="form.birth_date" max="{{ now()->toDateString() }}" class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
        @error('form.birth_date') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="preferred_barber_id" class="text-sm font-semibold text-slate-700">Barbero preferido <span class="font-normal text-slate-400">(opcional)</span></label>
        <select id="preferred_barber_id" wire:model="form.preferred_barber_id" class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
            <option value="">Sin preferencia</option>
            @foreach ($barbers as $barber)
                <option value="{{ $barber->id }}">{{ $barber->name }}</option>
            @endforeach
        </select>
        @error('form.preferred_barber_id') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="sm:col-span-2">
        <label for="notes" class="text-sm font-semibold text-slate-700">Notas</label>
        <textarea id="notes" wire:model="form.notes" rows="5" maxlength="2000" placeholder="Preferencias, observaciones o información relevante..." class="mt-2 block w-full resize-y rounded-xl border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
        @error('form.notes') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="sm:col-span-2">
        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4">
            <input type="checkbox" wire:model="form.is_active" class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            <span><span class="block text-sm font-semibold text-slate-800">Cliente activo</span><span class="mt-0.5 block text-xs text-slate-500">Los clientes inactivos permanecen en el historial y pueden consultarse.</span></span>
        </label>
        @error('form.is_active') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
</div>
