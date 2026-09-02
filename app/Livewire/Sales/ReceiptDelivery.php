<?php

namespace App\Livewire\Sales;

use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppMessageType;
use App\Models\Sale;
use App\Services\WhatsAppNotificationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class ReceiptDelivery extends Component
{
    use AuthorizesRequests;

    public bool $show = false;

    public ?int $saleId = null;

    public ?string $deliveryMessage = null;

    #[On('sale-paid')]
    public function open(int $saleId): void
    {
        $sale = Sale::query()->findOrFail($saleId);
        $this->authorize('view', $sale);

        $this->saleId = $sale->id;
        $this->deliveryMessage = null;
        $this->show = true;
    }

    public function close(): void
    {
        $this->show = false;
        $this->saleId = null;
        $this->deliveryMessage = null;
    }

    public function sendWhatsApp(WhatsAppNotificationService $whatsApp): void
    {
        $sale = $this->sale();
        $message = $sale->whatsappMessages()
            ->where('type', WhatsAppMessageType::Ticket->value)
            ->latest('id')
            ->first();

        if (! $message) {
            $message = $whatsApp->ticket($sale, auth()->user());
        } elseif (in_array($message->status, [WhatsAppMessageStatus::Failed, WhatsAppMessageStatus::Skipped], true)) {
            $message = $whatsApp->retry($message, auth()->user());
        }

        $this->deliveryMessage = match ($message->status) {
            WhatsAppMessageStatus::Pending => 'El ticket quedó en la cola de envío.',
            WhatsAppMessageStatus::Sent => 'El ticket ya fue enviado por WhatsApp.',
            WhatsAppMessageStatus::Delivered => 'El ticket ya fue entregado por WhatsApp.',
            WhatsAppMessageStatus::Read => 'El cliente ya leyó el ticket.',
            WhatsAppMessageStatus::Failed, WhatsAppMessageStatus::Skipped => $message->last_error
                ?? 'No fue posible enviar el ticket. Revisa el teléfono y el consentimiento del cliente.',
        };
    }

    public function render(): View
    {
        $sale = $this->saleId
            ? Sale::query()
                ->with(['client:id,whatsapp_opt_in', 'whatsappMessages' => fn ($query) => $query
                    ->where('type', WhatsAppMessageType::Ticket->value)
                    ->latest('id')])
                ->find($this->saleId)
            : null;

        return view('livewire.sales.receipt-delivery', [
            'sale' => $sale,
            'ticketMessage' => $sale?->whatsappMessages->first(),
        ]);
    }

    private function sale(): Sale
    {
        $sale = Sale::query()->findOrFail($this->saleId);
        $this->authorize('view', $sale);

        return $sale;
    }
}
