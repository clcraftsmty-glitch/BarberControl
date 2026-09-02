<div>
    <x-slot name="header">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-emerald-600">Comunicación con clientes</p>
            <h1 class="text-xl font-bold text-slate-900">WhatsApp</h1>
        </div>
    </x-slot>

    @if (session('status'))
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('status') }}</div>
    @endif
    @error('retry')
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ $message }}</div>
    @enderror

    <section class="rounded-2xl border p-5 shadow-sm {{ $isMetaConfigured ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }}">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-start gap-3">
                <span class="mt-1 h-3 w-3 rounded-full {{ $isMetaConfigured ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                <div>
                    <h2 class="font-black text-slate-950">{{ $isMetaConfigured ? 'Meta Cloud API configurada' : 'Modo de simulación activo' }}</h2>
                    <p class="mt-1 text-sm text-slate-600">{{ $isMetaConfigured ? 'Los mensajes se enviarán mediante la cuenta oficial conectada.' : 'Los mensajes se registran como simulados. Agrega las credenciales para activar envíos reales.' }}</p>
                </div>
            </div>
            <div class="rounded-xl bg-white/80 px-4 py-3 text-xs text-slate-600 ring-1 ring-black/5">
                <strong class="block text-slate-800">Webhook de Meta</strong>
                <code class="mt-1 block break-all">{{ route('whatsapp.webhook') }}</code>
            </div>
        </div>
    </section>

    <div class="mt-5 grid gap-3 sm:grid-cols-3 xl:grid-cols-6">
        @foreach ($statuses as $status)
            <button wire:click="$set('statusFilter', '{{ $status->value }}')" type="button" class="rounded-2xl border border-slate-200 bg-white p-4 text-left shadow-sm hover:border-emerald-300">
                <strong class="text-2xl font-black text-slate-950">{{ $counts->get($status->value, 0) }}</strong>
                <span class="mt-2 block text-xs font-bold {{ $status->badgeClasses() }} w-fit rounded-full px-2.5 py-1">{{ $status->label() }}</span>
            </button>
        @endforeach
    </div>

    <section class="mt-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 md:grid-cols-[minmax(260px,1fr)_220px_190px_auto]">
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="Cliente, teléfono, folio o ID de Meta" class="rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
            <select wire:model.live="typeFilter" class="rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                <option value="">Todos los tipos</option>
                @foreach ($types as $type)<option value="{{ $type->value }}">{{ $type->label() }}</option>@endforeach
            </select>
            <select wire:model.live="statusFilter" class="rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                <option value="">Todos los estados</option>
                @foreach ($statuses as $status)<option value="{{ $status->value }}">{{ $status->label() }}</option>@endforeach
            </select>
            <button wire:click="clearFilters" type="button" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">Limpiar</button>
        </div>
    </section>

    <section class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        @if ($messages->isEmpty())
            <div class="px-6 py-14 text-center"><p class="font-bold text-slate-700">No hay mensajes con estos filtros.</p></div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50 text-left text-[11px] font-extrabold uppercase tracking-wide text-slate-500">
                        <tr><th class="px-5 py-3">Cliente</th><th class="px-5 py-3">Notificación</th><th class="px-5 py-3">Estado</th><th class="px-5 py-3">Fechas</th><th class="px-5 py-3">Detalle</th><th class="px-5 py-3 text-right">Acción</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($messages as $message)
                            <tr wire:key="whatsapp-{{ $message->id }}" class="align-top hover:bg-slate-50/70">
                                <td class="px-5 py-4"><p class="font-bold text-slate-900">{{ $message->client->full_name }}</p><p class="mt-1 text-xs text-slate-500">+{{ $message->recipient }}</p><span class="mt-2 inline-flex text-[10px] font-bold {{ $message->client->whatsapp_opt_in ? 'text-emerald-700' : 'text-amber-700' }}">{{ $message->client->whatsapp_opt_in ? 'Consentimiento activo' : 'Sin consentimiento' }}</span></td>
                                <td class="px-5 py-4"><p class="text-sm font-bold text-slate-900">{{ $message->type->label() }}</p><p class="mt-1 text-xs text-slate-500">{{ $message->template_name }}</p>@if($message->sale)<a href="{{ route('sales.show', $message->sale) }}" wire:navigate class="mt-2 inline-flex text-xs font-bold text-brand-700">{{ $message->sale->folio }}</a>@endif</td>
                                <td class="px-5 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-extrabold uppercase {{ $message->status->badgeClasses() }}">{{ $message->status->label() }}</span><p class="mt-2 text-xs text-slate-500">{{ $message->attempts }} intento{{ $message->attempts === 1 ? '' : 's' }}</p></td>
                                <td class="whitespace-nowrap px-5 py-4 text-xs text-slate-500"><p>Creado: {{ $message->created_at->format('d/m H:i') }}</p>@if($message->delivered_at)<p class="mt-1">Entregado: {{ $message->delivered_at->format('d/m H:i') }}</p>@endif @if($message->read_at)<p class="mt-1">Leído: {{ $message->read_at->format('d/m H:i') }}</p>@endif</td>
                                <td class="max-w-xs px-5 py-4"><p class="break-all text-xs text-slate-500">{{ $message->provider_message_id ?: 'Sin ID del proveedor' }}</p>@if($message->last_error)<p class="mt-2 text-xs font-semibold text-red-700">{{ $message->last_error }}</p>@endif</td>
                                <td class="px-5 py-4 text-right">@if(in_array($message->status, [\App\Enums\WhatsAppMessageStatus::Failed, \App\Enums\WhatsAppMessageStatus::Skipped], true))<button wire:click="retry({{ $message->id }})" wire:loading.attr="disabled" type="button" class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-700 disabled:opacity-50">Reintentar</button>@else<span class="text-xs text-slate-400">—</span>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-5 py-4">{{ $messages->links() }}</div>
        @endif
    </section>
</div>
