<?php

namespace App\Livewire\Clients;

use App\Models\Client;
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
    public string $status = 'all';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function deactivate(Client $client): void
    {
        $this->authorize('deactivate', $client);

        $client->update(['is_active' => false]);

        session()->flash('status', "{$client->full_name} fue desactivado.");
    }

    public function render(): View
    {
        $this->authorize('viewAny', Client::class);

        $clients = Client::query()
            ->with('preferredBarber:id,name')
            ->search($this->search)
            ->when($this->status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($this->status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(10);

        return view('livewire.clients.index', compact('clients'))
            ->layout('layouts.app');
    }
}
