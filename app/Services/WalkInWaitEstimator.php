<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\WalkInEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class WalkInWaitEstimator
{
    public function __construct(private BarberOccupancyService $occupancy) {}

    /** @param Collection<int, WalkInEntry> $entries */
    public function estimate(Collection $entries, CarbonImmutable $now): Collection
    {
        $activeBarberIds = $this->occupancy->activeBarberIds();

        /** @var array<int, CarbonImmutable> $queueAvailableAt */
        $queueAvailableAt = [];

        foreach ($entries as $entry) {
            $waits = [];
            $starts = [];
            $busyBarberIds = [];

            foreach ($entry->service->barbers->where('is_active', true) as $barber) {
                if ($activeBarberIds->contains($barber->id)) {
                    $starts[$barber->id] = null;
                    $waits[$barber->id] = null;
                    $busyBarberIds[] = $barber->id;

                    continue;
                }

                $availableAfter = $queueAvailableAt[$barber->id] ?? $now;
                $start = $this->nextAvailableStart(
                    $barber,
                    $entry->service->duration_minutes,
                    $availableAfter,
                );
                $starts[$barber->id] = $start;
                $waits[$barber->id] = $start
                    ? max(0, (int) ceil(($start->timestamp - $now->timestamp) / 60))
                    : null;
            }

            $preferredId = $entry->preferred_barber_id;
            $assignedBarberId = $preferredId && ($starts[$preferredId] ?? null)
                ? $preferredId
                : collect($starts)
                    ->filter()
                    ->sortBy(fn (CarbonImmutable $start) => $start->timestamp)
                    ->keys()
                    ->first();
            $estimatedWait = $assignedBarberId ? $waits[$assignedBarberId] : null;

            if ($assignedBarberId && $starts[$assignedBarberId]) {
                $queueAvailableAt[$assignedBarberId] = $starts[$assignedBarberId]
                    ->addMinutes($entry->service->duration_minutes);
            }

            $entry->setAttribute('barber_wait_minutes', $waits);
            $entry->setAttribute('busy_barber_ids', $busyBarberIds);
            $entry->setAttribute('estimated_wait_minutes', $estimatedWait);
        }

        return $entries;
    }

    private function nextAvailableStart(
        Barber $barber,
        int $durationMinutes,
        CarbonImmutable $availableAfter,
    ): ?CarbonImmutable {
        $day = strtolower($availableAfter->englishDayOfWeek);
        $schedule = $barber->work_schedule[$day] ?? null;

        if (! ($schedule['enabled'] ?? false) || blank($schedule['start'] ?? null) || blank($schedule['end'] ?? null)) {
            return null;
        }

        $workStart = $availableAfter->setTimeFromTimeString($schedule['start']);
        $workEnd = $availableAfter->setTimeFromTimeString($schedule['end']);
        $candidate = $availableAfter->greaterThan($workStart) ? $availableAfter : $workStart;

        $appointments = Appointment::query()
            ->where('barber_id', $barber->id)
            ->whereDate('starts_at', $availableAfter->toDateString())
            ->where('ends_at', '>', $candidate)
            ->whereNotIn('status', [
                AppointmentStatus::Cancelled->value,
                AppointmentStatus::NoShow->value,
                AppointmentStatus::Rescheduled->value,
            ])
            ->orderBy('starts_at')
            ->get(['starts_at', 'ends_at']);

        foreach ($appointments as $appointment) {
            $appointmentStart = CarbonImmutable::instance($appointment->starts_at);
            $appointmentEnd = CarbonImmutable::instance($appointment->ends_at);

            if ($candidate->addMinutes($durationMinutes)->lessThanOrEqualTo($appointmentStart)) {
                break;
            }

            if ($candidate->lessThan($appointmentEnd)) {
                $candidate = $appointmentEnd;
            }
        }

        return $candidate->addMinutes($durationMinutes)->lessThanOrEqualTo($workEnd)
            ? $candidate
            : null;
    }
}
