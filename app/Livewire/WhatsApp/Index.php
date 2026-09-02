<?php

namespace App\Livewire\WhatsApp;

use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppMessageType;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    #[Url]
    public string $typeFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', WhatsAppMessage::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'statusFilter', 'typeFilter'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'typeFilter']);
        $this->resetPage();
    }

    public function retry(int $messageId, WhatsAppNotificationService $notifications): void
    {
        $message = WhatsAppMessage::query()->findOrFail($messageId);
        $this->authorize('retry', $message);

        $message = $notifications->retry($message, auth()->user());

        if ($message->status === WhatsAppMessageStatus::Skipped) {
            $this->addError('retry', $message->last_error);

            return;
        }

        session()->flash('status', 'Mensaje enviado nuevamente a la cola.');
    }

    public function render(): View
    {
        $query = WhatsAppMessage::query()
            ->with(['client:id,first_name,last_name,phone,whatsapp_opt_in', 'appointment:id,starts_at', 'sale:id,folio']);

        $messages = (clone $query)
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $search = trim($this->search);
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('recipient', 'like', "%{$search}%")
                        ->orWhere('provider_message_id', 'like', "%{$search}%")
                        ->orWhereHas('client', fn (Builder $client) => $client
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%"))
                        ->orWhereHas('sale', fn (Builder $sale) => $sale->where('folio', 'like', "%{$search}%"));
                });
            })
            ->when($this->statusFilter !== '', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->when($this->typeFilter !== '', fn (Builder $query) => $query->where('type', $this->typeFilter))
            ->latest()
            ->paginate(20);

        $counts = WhatsAppMessage::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return view('livewire.whatsapp.index', [
            'messages' => $messages,
            'counts' => $counts,
            'statuses' => WhatsAppMessageStatus::cases(),
            'types' => WhatsAppMessageType::cases(),
            'isMetaConfigured' => config('whatsapp.driver') === 'meta'
                && filled(config('whatsapp.phone_number_id'))
                && filled(config('whatsapp.access_token')),
        ])->layout('layouts.app');
    }
}
