<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use App\Services\ClientHistoryService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\View\View;
use Livewire\Component;

class Show extends Component
{
    use AuthorizesRequests;

    public Client $client;

    public function mount(Client $client): void
    {
        $this->authorize('view', $client);
        $this->client = $client->load('preferredBarber:id,name');
    }

    public function deactivate(): void
    {
        $this->authorize('deactivate', $this->client);

        $this->client->update(['is_active' => false]);
        $this->client->refresh();

        session()->flash('status', 'Cliente desactivado correctamente.');
    }

    public function render(ClientHistoryService $history): View
    {
        return view('livewire.clients.show', $history->forClient($this->client, auth()->user()))
            ->layout('layouts.app');
    }
}
