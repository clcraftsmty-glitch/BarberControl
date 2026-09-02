<?php

namespace App\Livewire\Appointments;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Livewire\Forms\AppointmentForm;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BusinessSetting;
use App\Models\Client;
use App\Models\Service;
use App\Services\AppointmentScheduler;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
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

    public string $month = '';

    public bool $showScheduleOverrideModal = false;

    /** @var array<int, string> */
    public array $scheduleWarnings = [];

    public function mount(): void
    {
        $this->authorize('viewAny', Appointment::class);
        $this->month = now()->format('Y-m');

        if (auth()->user()->role === UserRole::Barber) {
            $this->barberFilter = (string) (auth()->user()->barberProfile?->id ?? '');
        }

        if (request()->boolean('new') && auth()->user()->can('create', Appointment::class)) {
            $this->openCreate();
        }
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
        $this->showScheduleOverrideModal = false;
        $this->scheduleWarnings = [];
        $this->resetValidation();
    }

    public function updatedBarberFilter(): void
    {
        $this->dispatch('barber-filter-changed');
    }

    public function previousMonth(): void
    {
        $this->month = $this->monthStart()->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->month = $this->monthStart()->addMonth()->format('Y-m');
    }

    public function currentMonth(): void
    {
        $this->month = now()->format('Y-m');
    }

    public function createOnDate(string $date): void
    {
        $settings = BusinessSetting::current();
        $day = strtolower(CarbonImmutable::parse($date)->englishDayOfWeek);
        $opening = $settings->general_schedule[$day]['start'] ?? '09:00';
        $this->openCreate(CarbonImmutable::parse($date)->setTimeFromTimeString($opening)->toIso8601String());
    }

    public function updatedFormServiceId(mixed $serviceId): void
    {
        $service = Service::query()->find($serviceId);

        if ($service) {
            $this->form->price = $service->price;
        }
    }

    public function save(AppointmentScheduler $scheduler, bool $allowOverride = false): void
    {
        try {
            if ($this->form->appointment) {
                $this->authorize('update', $this->form->appointment);
                $this->form->update($scheduler, $allowOverride);
                $message = 'Cita actualizada correctamente.';
            } else {
                $this->authorize('create', Appointment::class);
                $this->form->store($scheduler, $allowOverride);
                $message = 'Cita creada correctamente.';
            }
        } catch (ValidationException $exception) {
            $scheduleWarnings = collect($exception->errors())
                ->filter(fn ($messages, $key) => str_starts_with($key, 'schedule.'))
                ->flatten()
                ->values()
                ->all();

            if ($scheduleWarnings !== [] && auth()->user()->role === UserRole::Administrator) {
                $this->scheduleWarnings = $scheduleWarnings;
                $this->showScheduleOverrideModal = true;

                return;
            }

            throw $exception;
        }

        $this->showModal = false;
        $this->showScheduleOverrideModal = false;
        $this->scheduleWarnings = [];
        session()->flash('status', $message);
        $this->dispatch('appointments-changed');
    }

    public function confirmScheduleOverride(AppointmentScheduler $scheduler): void
    {
        abort_unless(auth()->user()->role === UserRole::Administrator, 403);
        $this->save($scheduler, true);
    }

    /** @return array{ok: bool, message?: string, start?: string, end?: string} */
    public function moveAppointment(int $appointmentId, string $startsAt, AppointmentScheduler $scheduler): array
    {
        return $this->performMove($appointmentId, $startsAt, $scheduler, false);
    }

    /** @return array{ok: bool, message?: string, start?: string, end?: string, requiresConfirmation?: bool} */
    public function moveAppointmentWithOverride(int $appointmentId, string $startsAt, AppointmentScheduler $scheduler): array
    {
        abort_unless(auth()->user()->role === UserRole::Administrator, 403);

        return $this->performMove($appointmentId, $startsAt, $scheduler, true);
    }

    /** @return array{ok: bool, message?: string, start?: string, end?: string, requiresConfirmation?: bool} */
    private function performMove(
        int $appointmentId,
        string $startsAt,
        AppointmentScheduler $scheduler,
        bool $allowOverride,
    ): array {
        $appointment = Appointment::query()->findOrFail($appointmentId);
        $this->authorize('update', $appointment);

        try {
            $appointment = $scheduler->move($appointment, $startsAt, auth()->user(), $allowOverride);

            return [
                'ok' => true,
                'start' => $appointment->starts_at->toIso8601String(),
                'end' => $appointment->ends_at->toIso8601String(),
            ];
        } catch (ValidationException $exception) {
            return [
                'ok' => false,
                'message' => collect($exception->errors())->flatten()->first() ?? 'No fue posible mover la cita.',
                'requiresConfirmation' => auth()->user()->role === UserRole::Administrator
                    && collect($exception->errors())->keys()->contains(fn ($key) => str_starts_with($key, 'schedule.')),
            ];
        }
    }

    public function render(): View
    {
        $currentBarberId = auth()->user()->role === UserRole::Barber
            ? auth()->user()->barberProfile?->id
            : null;
        $barbers = Barber::query()
            ->where('is_active', true)
            ->when($currentBarberId, fn ($query) => $query->whereKey($currentBarberId))
            ->orderBy('display_name')
            ->get();
        $clients = Client::query()->where('is_active', true)->orderBy('first_name')->orderBy('last_name')->get();
        $services = Service::query()
            ->where('is_active', true)
            ->when($this->form->barber_id, fn ($query, $barberId) => $query
                ->whereHas('barbers', fn ($query) => $query->whereKey($barberId)))
            ->orderBy('name')
            ->get();
        $settings = BusinessSetting::current();

        $monthStart = $this->monthStart();
        $gridStart = $monthStart->startOfWeek(CarbonInterface::MONDAY);
        $gridEnd = $monthStart->endOfMonth()->endOfWeek(CarbonInterface::SUNDAY);
        $selectedBarberId = $currentBarberId ?: (filled($this->barberFilter) ? (int) $this->barberFilter : null);
        $monthlyAppointments = Appointment::query()
            ->with([
                'client:id,first_name,last_name',
                'barber:id,display_name',
                'service:id,name',
            ])
            ->whereBetween('starts_at', [$gridStart->startOfDay(), $gridEnd->endOfDay()])
            ->when($selectedBarberId, fn ($query) => $query->where('barber_id', $selectedBarberId))
            ->orderBy('starts_at')
            ->get()
            ->groupBy(fn (Appointment $appointment) => $appointment->starts_at->format('Y-m-d'));
        $calendarWeeks = collect(range(0, $gridStart->diffInDays($gridEnd)))
            ->map(fn (int $offset) => $gridStart->addDays($offset))
            ->chunk(7);

        return view('livewire.appointments.calendar', [
            'barbers' => $barbers,
            'clients' => $clients,
            'services' => $services,
            'monthStart' => $monthStart,
            'calendarWeeks' => $calendarWeeks,
            'monthlyAppointments' => $monthlyAppointments,
            'statuses' => AppointmentStatus::cases(),
            'canManage' => auth()->user()->can('create', Appointment::class),
            'slotDuration' => gmdate('H:i:s', $settings->default_appointment_duration_minutes * 60),
        ])->layout('layouts.app');
    }

    private function monthStart(): CarbonImmutable
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $this->month)) {
            return CarbonImmutable::now()->startOfMonth();
        }

        return CarbonImmutable::createFromFormat('Y-m-d', $this->month.'-01')->startOfMonth();
    }
}
