<?php

namespace App\Livewire\Forms;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Services\AppointmentScheduler;
use Illuminate\Validation\Rule;
use Livewire\Form;

class AppointmentForm extends Form
{
    public ?Appointment $appointment = null;

    public ?int $client_id = null;

    public ?int $barber_id = null;

    public ?int $service_id = null;

    public string $starts_at = '';

    public string $price = '';

    public string $status = 'pendiente';

    public ?string $notes = null;

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')->where('is_active', true)],
            'barber_id' => ['required', 'integer', Rule::exists('barbers', 'id')->where('is_active', true)],
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where('is_active', true)],
            'starts_at' => ['required', 'date'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'status' => ['required', Rule::enum(AppointmentStatus::class)],
            'notes' => ['nullable', 'string', 'max:3000'],
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'client_id' => 'cliente',
            'barber_id' => 'barbero',
            'service_id' => 'servicio',
            'starts_at' => 'fecha y hora de inicio',
            'price' => 'precio',
            'status' => 'estado',
            'notes' => 'notas',
        ];
    }

    public function setAppointment(Appointment $appointment): void
    {
        $this->appointment = $appointment;
        $this->client_id = $appointment->client_id;
        $this->barber_id = $appointment->barber_id;
        $this->service_id = $appointment->service_id;
        $this->starts_at = $appointment->starts_at->format('Y-m-d\TH:i');
        $this->price = $appointment->price;
        $this->status = $appointment->status->value;
        $this->notes = $appointment->notes;
    }

    public function clear(?string $startsAt = null, ?int $barberId = null): void
    {
        $this->reset();
        $this->starts_at = $startsAt ?? now()->ceilMinutes(30)->format('Y-m-d\TH:i');
        $this->barber_id = $barberId;
        $this->status = AppointmentStatus::Pending->value;
    }

    public function store(AppointmentScheduler $scheduler): Appointment
    {
        return $scheduler->create($this->validatedData(), auth()->user());
    }

    public function update(AppointmentScheduler $scheduler): Appointment
    {
        return $scheduler->update($this->appointment, $this->validatedData(), auth()->user());
    }

    private function validatedData(): array
    {
        $data = $this->validate();

        return [
            ...$data,
            'client_id' => (int) $data['client_id'],
            'barber_id' => (int) $data['barber_id'],
            'service_id' => (int) $data['service_id'],
            'price' => number_format((float) $data['price'], 2, '.', ''),
            'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
        ];
    }
}
