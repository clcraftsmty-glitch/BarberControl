<?php

namespace App\Livewire\Barbers;

use App\Models\Barber;
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

    public function changeStatus(Barber $barber): void
    {
        $this->authorize('changeStatus', $barber);
        $barber->update(['is_active' => ! $barber->is_active]);

        session()->flash('status', "El barbero {$barber->display_name} fue ".($barber->is_active ? 'activado' : 'desactivado').'.');
    }

    public function render(): View
    {
        $this->authorize('viewAny', Barber::class);

        $barbers = Barber::query()
            ->with('user:id,name,email')
            ->withCount('services')
            ->search($this->search)
            ->when($this->status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($this->status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('display_name')
            ->paginate(10);

        return view('livewire.barbers.index', compact('barbers'))->layout('layouts.app');
    }
}
