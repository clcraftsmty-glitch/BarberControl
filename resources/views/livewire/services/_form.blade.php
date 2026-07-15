<div class="grid gap-5 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label for="name" class="text-sm font-semibold text-slate-700">Nombre <span class="text-brand-600">*</span></label>
        <input id="name" type="text" wire:model="form.name" maxlength="150" class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
        @error('form.name') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="sm:col-span-2">
        <label for="description" class="text-sm font-semibold text-slate-700">Descripción <span class="text-brand-600">*</span></label>
        <textarea id="description" wire:model="form.description" rows="5" maxlength="2000" class="mt-2 block w-full resize-y rounded-xl border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
        @error('form.description') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="duration_minutes" class="text-sm font-semibold text-slate-700">Duración en minutos <span class="text-brand-600">*</span></label>
        <input id="duration_minutes" type="number" wire:model="form.duration_minutes" min="1" max="1440" step="1" class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
        @error('form.duration_minutes') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="price" class="text-sm font-semibold text-slate-700">Precio <span class="text-brand-600">*</span></label>
        <div class="relative mt-2"><span class="pointer-events-none absolute left-3 top-2.5 text-sm text-slate-500">$</span><input id="price" type="number" wire:model="form.price" min="0" max="99999999.99" step="0.01" class="block w-full rounded-xl border-slate-300 pl-7 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"></div>
        @error('form.price') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="commission_percentage" class="text-sm font-semibold text-slate-700">Porcentaje de comisión <span class="text-brand-600">*</span></label>
        <div class="relative mt-2"><input id="commission_percentage" type="number" wire:model="form.commission_percentage" min="0" max="100" step="0.01" class="block w-full rounded-xl border-slate-300 pr-8 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"><span class="pointer-events-none absolute right-3 top-2.5 text-sm text-slate-500">%</span></div>
        @error('form.commission_percentage') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="flex items-end">
        <label class="flex w-full cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4"><input type="checkbox" wire:model="form.is_active" class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500"><span><span class="block text-sm font-semibold text-slate-800">Servicio activo</span><span class="block text-xs text-slate-500">Disponible para la operación.</span></span></label>
    </div>
</div>
