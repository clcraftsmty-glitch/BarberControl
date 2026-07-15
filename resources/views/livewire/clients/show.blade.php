<div class="mx-auto max-w-5xl space-y-6">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <a href="{{ route('clients.index') }}" wire:navigate class="text-sm font-semibold text-brand-600 hover:text-brand-700">← Volver a clientes</a>
            <div class="mt-3 flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-extrabold tracking-tight text-slate-950">{{ $client->full_name }}</h1>
                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $client->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">{{ $client->is_active ? 'Activo' : 'Inactivo' }}</span>
            </div>
            <p class="mt-1 text-sm text-slate-500">Cliente registrado el {{ $client->created_at->format('d/m/Y') }}.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('update', $client)
                <a href="{{ route('clients.edit', $client) }}" wire:navigate class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Editar</a>
            @endcan
            @if ($client->is_active)
                @can('deactivate', $client)
                    <button type="button" wire:click="deactivate" wire:confirm="¿Desactivar a {{ $client->full_name }}?" class="inline-flex items-center justify-center rounded-xl bg-red-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-red-700">Desactivar</button>
                @endcan
            @endif
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
            <h2 class="text-base font-bold text-slate-900">Información de contacto</h2>
            <dl class="mt-5 grid gap-5 sm:grid-cols-2">
                <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Teléfono</dt><dd class="mt-1 text-sm font-semibold text-slate-800"><a href="tel:{{ $client->phone }}" class="hover:text-brand-600">{{ $client->phone }}</a></dd></div>
                <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Correo</dt><dd class="mt-1 text-sm font-semibold text-slate-800">@if($client->email)<a href="mailto:{{ $client->email }}" class="hover:text-brand-600">{{ $client->email }}</a>@else Sin información @endif</dd></div>
                <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Fecha de nacimiento</dt><dd class="mt-1 text-sm font-semibold text-slate-800">{{ $client->birth_date?->format('d/m/Y') ?: 'Sin información' }}</dd></div>
                <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Barbero preferido</dt><dd class="mt-1 text-sm font-semibold text-slate-800">{{ $client->preferredBarber?->name ?: 'Sin preferencia' }}</dd></div>
            </dl>
        </section>

        <aside class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-bold text-slate-900">Resumen</h2>
            <dl class="mt-5 space-y-4">
                <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Identificador</dt><dd class="mt-1 text-sm font-semibold text-slate-800">#{{ $client->id }}</dd></div>
                <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Última actualización</dt><dd class="mt-1 text-sm font-semibold text-slate-800">{{ $client->updated_at->format('d/m/Y H:i') }}</dd></div>
            </dl>
        </aside>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-base font-bold text-slate-900">Notas</h2>
        <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-600">{{ $client->notes ?: 'No hay notas registradas para este cliente.' }}</p>
    </section>
</div>
