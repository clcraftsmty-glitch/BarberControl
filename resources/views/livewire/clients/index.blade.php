<div class="mx-auto max-w-7xl space-y-6">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-brand-600">Directorio</p>
            <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-slate-950">Clientes</h1>
            <p class="mt-1 text-sm text-slate-500">Consulta y administra la información de tus clientes.</p>
        </div>
        @can('create', App\Models\Client::class)
            <a href="{{ route('clients.create') }}" wire:navigate class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-brand-700">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                Nuevo cliente
            </a>
        @endcan
    </div>

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="grid gap-3 border-b border-slate-200 p-4 sm:grid-cols-[minmax(0,1fr)_180px]">
            <label class="relative block">
                <span class="sr-only">Buscar clientes</span>
                <svg class="pointer-events-none absolute left-3 top-3 h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m21 21-4.35-4.35m1.35-5.4a6.75 6.75 0 1 1-13.5 0 6.75 6.75 0 0 1 13.5 0Z" /></svg>
                <input type="search" wire:model.live.debounce.350ms="search" placeholder="Buscar por nombre o teléfono..." class="block w-full rounded-xl border-slate-300 py-2.5 pl-10 text-sm focus:border-brand-500 focus:ring-brand-500">
            </label>
            <select wire:model.live="status" aria-label="Filtrar por estado" class="rounded-xl border-slate-300 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="all">Todos</option>
                <option value="active">Activos</option>
                <option value="inactive">Inactivos</option>
            </select>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Cliente</th>
                        <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Contacto</th>
                        <th class="hidden px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 lg:table-cell">Barbero preferido</th>
                        <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Estado</th>
                        <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($clients as $client)
                        <tr wire:key="client-{{ $client->id }}" class="hover:bg-slate-50/80">
                            <td class="whitespace-nowrap px-5 py-4">
                                <a href="{{ route('clients.show', $client) }}" wire:navigate class="flex items-center gap-3">
                                    <span class="flex h-10 w-10 items-center justify-center rounded-full bg-brand-50 text-sm font-bold text-brand-700">{{ str($client->first_name)->substr(0, 1)->upper() }}{{ str($client->last_name)->substr(0, 1)->upper() }}</span>
                                    <span><span class="block text-sm font-semibold text-slate-900">{{ $client->full_name }}</span><span class="block text-xs text-slate-500">Cliente #{{ $client->id }}</span></span>
                                </a>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4"><p class="text-sm font-medium text-slate-700">{{ $client->phone }}</p><p class="text-xs text-slate-500">{{ $client->email ?: 'Sin correo' }}</p></td>
                            <td class="hidden whitespace-nowrap px-5 py-4 text-sm text-slate-600 lg:table-cell">{{ $client->preferredBarber?->name ?: 'Sin preferencia' }}</td>
                            <td class="whitespace-nowrap px-5 py-4">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $client->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $client->is_active ? 'Activo' : 'Inactivo' }}</span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('clients.show', $client) }}" wire:navigate class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-100">Ver</a>
                                    @can('update', $client)
                                        <a href="{{ route('clients.edit', $client) }}" wire:navigate class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-brand-700 hover:bg-brand-50">Editar</a>
                                    @endcan
                                    @if ($client->is_active)
                                        @can('deactivate', $client)
                                            <button type="button" wire:click="deactivate({{ $client->id }})" wire:confirm="¿Desactivar a {{ $client->full_name }}?" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50">Desactivar</button>
                                        @endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-14 text-center"><p class="font-semibold text-slate-700">No se encontraron clientes</p><p class="mt-1 text-sm text-slate-500">Ajusta la búsqueda o registra un cliente nuevo.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($clients->hasPages())
            <div class="border-t border-slate-200 px-5 py-4">{{ $clients->links() }}</div>
        @endif
    </div>
</div>
