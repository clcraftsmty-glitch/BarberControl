<?php

namespace App\Livewire\Services;

use App\Models\Service;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\View\View;
use Livewire\Component;

class Show extends Component
{
    use AuthorizesRequests;

    public Service $service;

    public function mount(Service $service): void
    {
        $this->authorize('view', $service);
        $this->service = $service;
    }

    public function changeStatus(): void
    {
        $this->authorize('changeStatus', $this->service);
        $this->service->update(['is_active' => ! $this->service->is_active]);
        $this->service->refresh();

        session()->flash('status', 'Estado del servicio actualizado.');
    }

    public function render(): View
    {
        return view('livewire.services.show')->layout('layouts.app');
    }
}
