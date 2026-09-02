<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Barber;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class BarberOccupancyService
{
    public function assertCanStart(
        int $barberId,
        ?int $exceptAppointmentId = null,
        string $field = 'status',
    ): void {
        $barber = Barber::query()->lockForUpdate()->findOrFail($barberId);

        $activeService = Appointment::query()
            ->where('barber_id', $barber->id)
            ->where('status', AppointmentStatus::InService->value)
            ->when($exceptAppointmentId, fn ($query) => $query->whereKeyNot($exceptAppointmentId))
            ->lockForUpdate()
            ->first(['id']);

        if ($activeService) {
            throw ValidationException::withMessages([
                $field => "{$barber->display_name} ya tiene un servicio en curso. Finalízalo antes de iniciar otro.",
            ]);
        }
    }

    /** @return Collection<int, int> IDs de barberos con un servicio activo */
    public function activeBarberIds(): Collection
    {
        return Appointment::query()
            ->where('status', AppointmentStatus::InService->value)
            ->distinct()
            ->pluck('barber_id');
    }
}
