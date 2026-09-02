<?php

namespace App\Console\Commands;

use App\Enums\AppointmentStatus;
use App\Enums\WhatsAppMessageType;
use App\Models\Appointment;
use App\Services\WhatsAppNotificationService;
use Illuminate\Console\Command;

class QueueWhatsAppReminders extends Command
{
    protected $signature = 'whatsapp:queue-reminders';

    protected $description = 'Programa recordatorios de citas por WhatsApp para 24 y 2 horas antes';

    public function handle(WhatsAppNotificationService $notifications): int
    {
        $now = now();
        $appointments = Appointment::query()
            ->with(['client', 'barber', 'service'])
            ->whereIn('status', [AppointmentStatus::Pending->value, AppointmentStatus::Confirmed->value])
            ->whereBetween('starts_at', [$now->copy()->addMinutes(100), $now->copy()->addHours(25)])
            ->get();
        $queued = 0;

        foreach ($appointments as $appointment) {
            $minutes = (int) floor(($appointment->starts_at->timestamp - $now->timestamp) / 60);

            if ($minutes >= 1435 && $minutes <= 1445) {
                $notifications->reminder($appointment, WhatsAppMessageType::Reminder24Hours);
                $queued++;
            }

            if ($minutes >= 115 && $minutes <= 125) {
                $notifications->reminder($appointment, WhatsAppMessageType::Reminder2Hours);
                $queued++;
            }
        }

        $this->info("Recordatorios procesados: {$queued}");

        return self::SUCCESS;
    }
}
