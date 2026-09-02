<?php

namespace App\Livewire\Dashboard;

use App\Models\Appointment;
use App\Services\OperationalDashboardService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\View\View;
use Livewire\Component;

class Operational extends Component
{
    use AuthorizesRequests;

    public function mount(): void
    {
        $this->authorize('viewAny', Appointment::class);
    }

    public function render(OperationalDashboardService $dashboard): View
    {
        return view('livewire.dashboard.operational', $dashboard->metricsFor(auth()->user()))
            ->layout('layouts.app');
    }
}
