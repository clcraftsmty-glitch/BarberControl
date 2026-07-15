<div class="mx-auto max-w-4xl">
    <div class="mb-6">
        <a href="{{ route('clients.show', $form->client) }}" wire:navigate class="text-sm font-semibold text-brand-600 hover:text-brand-700">← Volver al detalle</a>
        <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-slate-950">Editar cliente</h1>
        <p class="mt-1 text-sm text-slate-500">Actualiza la información de {{ $form->client->full_name }}.</p>
    </div>

    <form wire:submit="save" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        @include('livewire.clients._form')

        <div class="mt-7 flex flex-col-reverse gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:justify-end">
            <a href="{{ route('clients.show', $form->client) }}" wire:navigate class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</a>
            <button type="submit" wire:loading.attr="disabled" class="inline-flex items-center justify-center rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-brand-700 disabled:opacity-60">
                <span wire:loading.remove wire:target="save">Guardar cambios</span>
                <span wire:loading wire:target="save">Guardando...</span>
            </button>
        </div>
    </form>
</div>
