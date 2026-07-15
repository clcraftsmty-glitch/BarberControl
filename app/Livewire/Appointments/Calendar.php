<?php

namespace App\Livewire\Appointments;

use App\Enums\AppointmentStatus;
use App\Livewire\Forms\AppointmentForm;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Service;
use App\Services\AppointmentScheduler;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;

class Calendar extends Component
{
    use AuthorizesRequests;

    public AppointmentForm $form;

    public bool $showModal = false;

    public string $barberFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Appointment::class);
    }

    public function openCreate(?string $startsAt = null): void
    {
        $this->authorize('create', Appointment::class);
        $this->resetValidation();
        $formattedStart = filled($startsAt)
            ? CarbonImmutable::parse($startsAt)->setTimezone(config('app.timezone'))->format('Y-m-d\TH:i')
            : null;
        $this->form->clear($formattedStart, filled($this->barberFilter) ? (int) $this->barberFilter : null);
        $this->showModal = true;
    }

    public function openEdit(int $appointmentId): void
    {
        $appointment = Appointment::query()
            ->with(['creator:id,name', 'updater:id,name'])
            ->findOrFail($appointmentId);

        $this->authorize('update', $appointment);
        $this->resetValidation();
        $this->form->setAppointment($appointment);
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetValidation();
    }

    public function updatedBarberFilter(): void
    {
        $this->dispatch('barber-filter-changed');
    }

    public function updatedFormServiceId(mixed $serviceId): void
    {
        $service = Service::query()->find($serviceId);

        if ($service) {
            $this->form->price = $service->price;
        }
    }

    public function save(AppointmentScheduler $scheduler): void
    {
        if ($this->form->appointment) {
            $this->authorize('update', $this->form->appointment);
            $this->form->update($scheduler);
            $message = 'Cita actualizada correctamente.';
        } else {
            $this->authorize('create', Appointment::class);
            $this->form->store($scheduler);
            $message = 'Cita creada correctamente.';
        }

        $this->showModal = false;
        session()->flash('status', $message);
        $this->dispatch('appointments-changed');
    }

    /** @return array{ok: bool, message?: string, start?: string, end?: string} */
    public function moveAppointment(int $appointmentId, string $startsAt, AppointmentScheduler $scheduler): array
    {
        $appointment = Appointment::query()->findOrFail($appointmentId);
        $this->authorize('update', $appointment);

        try {
            $appointment = $scheduler->move($appointment, $startsAt, auth()->user());

            return [
                'ok' => true,
                'start' => $appointment->starts_at->toIso8601String(),
                'end' => $appointment->ends_at->toIso8601String(),
            ];
        } catch (ValidationException $exception) {
            return [
                'ok' => false,
                'message' => collect($exception->errors())->flatten()->first() ?? 'No fue posible mover la cita.',
            ];
        }
    }

    public function render(): View
    {
        $barbers = Barber::query()->where('is_active', true)->orderBy('display_name')->get();
        $clients = Client::query()->where('is_active', true)->orderBy('first_name')->orderBy('last_name')->get();
        $services = Service::query()
            ->where('is_active', true)
            ->when($this->form->barber_id, fn ($query, $barberId) => $query
                ->whereHas('barbers', fn ($query) => $query->whereKey($barberId)))
            ->orderBy('name')
            ->get();

        return view('livewire.appointments.calendar', [
            'barbers' => $barbers,
            'clients' => $clients,
            'services' => $services,
            'statuses' => AppointmentStatus::cases(),
            'canManage' => auth()->user()->can('create', Appointment::class),
        ])->layout('layouts.app');
    }
}
