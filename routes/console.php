<?php

use App\Models\SystemError;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('whatsapp:queue-reminders')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command('backup:database')
    ->dailyAt(config('security.backups.run_at', '02:00'))
    ->withoutOverlapping(180)
    ->onOneServer()
    ->when(fn (): bool => (bool) config('security.backups.enabled'));

Schedule::call(fn () => SystemError::query()
    ->whereNotNull('resolved_at')
    ->where('last_occurred_at', '<', now()->subDays((int) config('security.monitoring.retention_days', 90)))
    ->delete())
    ->name('security:prune-resolved-errors')
    ->dailyAt('03:30')
    ->withoutOverlapping();
