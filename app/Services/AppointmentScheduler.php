<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentScheduler
{
    public function create(array $data, User $actor): Appointment
    {
        return DB::transaction(function () use ($data, $actor): Appointment {
            $context = $this->validatedContext($data);

            $appointment = new Appointment($this->attributes($data, $context));
            $appointment->created_by = $actor->id;
            $appointment->updated_by = $actor->id;
            $appointment->save();

            return $appointment->refresh();
        });
    }

    public function update(Appointment $appointment, array $data, User $actor): Appointment
    {
        return DB::transaction(function () use ($appointment, $data, $actor): Appointment {
            $appointment = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            $context = $this->validatedContext($data, $appointment);

            $appointment->fill($this->attributes($data, $context));
            $appointment->updated_by = $actor->id;
            $appointment->save();

            return $appointment->refresh();
        });
    }

    public function move(Appointment $appointment, string $startsAt, User $actor): Appointment
    {
        return $this->update($appointment, [
            'client_id' => $appointment->client_id,
            'barber_id' => $appointment->barber_id,
            'service_id' => $appointment->service_id,
            'starts_at' => $startsAt,
            'price' => $appointment->price,
            'status' => $appointment->status->value,
            'notes' => $appointment->notes,
        ], $actor);
    }

    /** @return array{barber: Barber, service: Service, start: CarbonImmutable, end: CarbonImmutable, status: AppointmentStatus} */
    private function validatedContext(array $data, ?Appointment $appointment = null): array
    {
        $barber = Barber::query()->lockForUpdate()->findOrFail($data['barber_id']);
        $service = Service::query()->findOrFail($data['service_id']);
        $client = Client::query()->findOrFail($data['client_id']);
        $status = AppointmentStatus::from($data['status']);
        $start = CarbonImmutable::parse($data['starts_at'])->seconds(0);
        $end = $start->addMinutes($service->duration_minutes);

        if (! $client->is_active) {
            $this->fail('form.client_id', 'El cliente seleccionado está inactivo.');
        }

        if (! $barber->is_active) {
            $this->fail('form.barber_id', 'El barbero seleccionado está inactivo.');
        }

        if (! $service->is_active) {
            $this->fail('form.service_id', 'El servicio seleccionado está inactivo.');
        }

        if (! $barber->services()->whereKey($service->id)->exists()) {
            $this->fail('form.service_id', 'El barbero seleccionado no realiza este servicio.');
        }

        $this->assertWithinWorkingHours($barber, $start, $end);

        if ($status !== AppointmentStatus::Cancelled) {
            $this->assertNoOverlap($barber, $start, $end, $appointment);
        }

        return compact('barber', 'service', 'start', 'end', 'status');
    }

    /** @param array{barber: Barber, service: Service, start: CarbonImmutable, end: CarbonImmutable, status: AppointmentStatus} $context */
    private function attributes(array $data, array $context): array
    {
        return [
            'client_id' => $data['client_id'],
            'barber_id' => $context['barber']->id,
            'service_id' => $context['service']->id,
            'starts_at' => $context['start'],
            'ends_at' => $context['end'],
            'price' => $data['price'],
            'status' => $context['status'],
            'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
        ];
    }

    private function assertWithinWorkingHours(Barber $barber, CarbonImmutable $start, CarbonImmutable $end): void
    {
        $day = strtolower($start->englishDayOfWeek);
        $schedule = $barber->work_schedule[$day] ?? null;

        if (! ($schedule['enabled'] ?? false) || blank($schedule['start'] ?? null) || blank($schedule['end'] ?? null)) {
            $this->fail('form.starts_at', 'El barbero no trabaja el día seleccionado.');
        }

        if (! $start->isSameDay($end)) {
            $this->fail('form.starts_at', 'La cita debe iniciar y terminar el mismo día.');
        }

        $workStart = $start->setTimeFromTimeString($schedule['start']);
        $workEnd = $start->setTimeFromTimeString($schedule['end']);

        if ($start->lessThan($workStart) || $end->greaterThan($workEnd)) {
            $this->fail(
                'form.starts_at',
                "La cita debe quedar dentro del horario de {$schedule['start']} a {$schedule['end']}.",
            );
        }
    }

    private function assertNoOverlap(Barber $barber, CarbonImmutable $start, CarbonImmutable $end, ?Appointment $appointment): void
    {
        $overlaps = Appointment::query()
            ->where('barber_id', $barber->id)
            ->where('status', '!=', AppointmentStatus::Cancelled->value)
            ->when($appointment, fn ($query) => $query->whereKeyNot($appointment->id))
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->lockForUpdate()
            ->exists();

        if ($overlaps) {
            $this->fail('form.starts_at', 'El barbero ya tiene una cita en ese horario.');
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
