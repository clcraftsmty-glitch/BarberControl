<div class="mx-auto max-w-4xl">
    <div class="mb-6"><a href="{{ route('services.index') }}" wire:navigate class="text-sm font-semibold text-brand-600">← Volver a servicios</a><h1 class="mt-2 text-2xl font-extrabold text-slate-950">Nuevo servicio</h1><p class="mt-1 text-sm text-slate-500">Define duración, precio y comisión.</p></div>
    <form wire:submit="save" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        @include('livewire.services._form')
        <div class="mt-7 flex flex-col-reverse gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:justify-end"><a href="{{ route('services.index') }}" wire:navigate class="inline-flex justify-center rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700">Cancelar</a><button type="submit" wire:loading.attr="disabled" class="rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-brand-700 disabled:opacity-60"><span wire:loading.remove wire:target="save">Guardar servicio</span><span wire:loading wire:target="save">Guardando...</span></button></div>
    </form>
</div>
