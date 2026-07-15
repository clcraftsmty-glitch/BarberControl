<?php

namespace App\Livewire\Services;

use App\Models\Service;
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

    public function changeStatus(Service $service): void
    {
        $this->authorize('changeStatus', $service);

        $service->update(['is_active' => ! $service->is_active]);

        session()->flash('status', "El servicio {$service->name} fue ".($service->is_active ? 'activado' : 'desactivado').'.');
    }

    public function render(): View
    {
        $this->authorize('viewAny', Service::class);

        $services = Service::query()
            ->search($this->search)
            ->when($this->status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($this->status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate(10);

        return view('livewire.services.index', compact('services'))->layout('layouts.app');
    }
}
