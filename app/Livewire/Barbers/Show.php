<?php

namespace App\Livewire\Barbers;

use App\Models\Barber;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\View\View;
use Livewire\Component;

class Show extends Component
{
    use AuthorizesRequests;

    public Barber $barber;

    public function mount(Barber $barber): void
    {
        $this->authorize('view', $barber);
        $this->barber = $barber->load(['user:id,name,email', 'services:id,name,duration_minutes,price']);
    }

    public function changeStatus(): void
    {
        $this->authorize('changeStatus', $this->barber);
        $this->barber->update(['is_active' => ! $this->barber->is_active]);
        $this->barber->refresh();

        session()->flash('status', 'Estado del barbero actualizado.');
    }

    public function render(): View
    {
        return view('livewire.barbers.show')->layout('layouts.app');
    }
}
