<div>
    @if ($show && $sale)
        <div class="fixed inset-0 z-[80] overflow-y-auto px-4 py-6" role="dialog" aria-modal="true" aria-labelledby="receipt-delivery-title">
            <button type="button" wire:click="close" class="fixed inset-0 bg-slate-950/65" aria-label="Cerrar"></button>

            <div class="relative mx-auto w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="border-b border-emerald-100 bg-emerald-50 px-5 py-5 text-center sm:px-6">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-500 text-white shadow-sm">
                        <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="m5 12 4 4L19 6" /></svg>
                    </div>
                    <p class="mt-3 text-xs font-black uppercase tracking-[0.18em] text-emerald-700">Cobro registrado</p>
                    <h3 id="receipt-delivery-title" class="mt-1 text-2xl font-black text-slate-950">Pago completado</h3>
                    <p class="mt-1 text-sm text-slate-600">Ticket {{ $sale->folio }} · ${{ number_format((float) $sale->total, 2) }}</p>
                </div>

                <div class="space-y-3 p-5 sm:p-6">
                    <p class="text-center text-sm font-semibold text-slate-600">¿Cómo quieres entregar el comprobante?</p>

                    <a href="{{ route('sales.ticket.print', $sale) }}?autoprint=1" target="_blank" class="flex w-full items-center gap-4 rounded-xl bg-slate-950 px-4 py-3.5 text-left text-white hover:bg-slate-800">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white/10"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6.75 7.5V3.75h10.5V7.5m-10.5 9v3.75h10.5V16.5m-12 0h13.5A2.25 2.25 0 0 0 21 14.25v-4.5A2.25 2.25 0 0 0 18.75 7.5H5.25A2.25 2.25 0 0 0 3 9.75v4.5a2.25 2.25 0 0 0 2.25 2.25Z" /></svg></span>
                        <span><strong class="block text-sm">Imprimir ahora</strong><small class="text-slate-300">Abre el ticket de 80 mm y el diálogo de impresión</small></span>
                    </a>

                    <button type="button" wire:click="sendWhatsApp" wire:loading.attr="disabled" wire:target="sendWhatsApp" class="flex w-full items-center gap-4 rounded-xl bg-emerald-600 px-4 py-3.5 text-left text-white hover:bg-emerald-700 disabled:opacity-60">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white/15"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8.25 10.5h.008v.008H8.25V10.5Zm3.75 0h.008v.008H12V10.5Zm3.75 0h.008v.008h-.008V10.5ZM21 11.25c0 4.142-4.03 7.5-9 7.5a10.61 10.61 0 0 1-3.76-.68L3 19.5l1.49-3.94A6.84 6.84 0 0 1 3 11.25c0-4.142 4.03-7.5 9-7.5s9 3.358 9 7.5Z" /></svg></span>
                        <span>
                            <strong class="block text-sm" wire:loading.remove wire:target="sendWhatsApp">
                                {{ $ticketMessage && in_array($ticketMessage->status, [\App\Enums\WhatsAppMessageStatus::Failed, \App\Enums\WhatsAppMessageStatus::Skipped], true) ? 'Reintentar por WhatsApp' : 'Enviar por WhatsApp' }}
                            </strong>
                            <strong class="block text-sm" wire:loading wire:target="sendWhatsApp">Enviando…</strong>
                            <small class="text-emerald-100">Usa el consentimiento y teléfono de la ficha del cliente</small>
                        </span>
                    </button>

                    <a href="{{ route('sales.ticket.pdf', $sale) }}" class="flex w-full items-center gap-4 rounded-xl border border-slate-300 bg-white px-4 py-3.5 text-left text-slate-800 hover:bg-slate-50">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M7.5 10.5 12 15m0 0 4.5-4.5M12 15V3" /></svg></span>
                        <span><strong class="block text-sm">Descargar PDF</strong><small class="text-slate-500">Guarda una copia digital del ticket</small></span>
                    </a>

                    @if ($deliveryMessage)
                        <div class="rounded-xl border px-4 py-3 text-sm font-semibold {{ $ticketMessage && in_array($ticketMessage->status, [\App\Enums\WhatsAppMessageStatus::Failed, \App\Enums\WhatsAppMessageStatus::Skipped], true) ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800' }}">
                            {{ $deliveryMessage }}
                        </div>
                    @endif
                </div>

                <div class="flex items-center justify-between gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:px-6">
                    <a href="{{ route('sales.show', $sale) }}" wire:navigate class="text-sm font-bold text-slate-600 hover:text-slate-900">Ver venta</a>
                    <button type="button" wire:click="close" class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-100">Terminar</button>
                </div>
            </div>
        </div>
    @endif
</div>
