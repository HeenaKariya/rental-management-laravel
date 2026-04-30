<?php

use App\Domain\Notifications\ReminderNotificationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('phase7:dispatch-reminders', function (ReminderNotificationService $service) {
    $result = $service->dispatch();

    $this->info('Reminder notification dispatch complete.');
    $this->line('Sent: '.$result['sent']);
    $this->line('Failed: '.$result['failed']);
})->purpose('Dispatch scheduled Phase 7 reminder notifications.');

Artisan::command('phase7:retry-failed-notifications', function (ReminderNotificationService $service) {
    $result = $service->retryFailed();

    $this->info('Failed notification retry complete.');
    $this->line('Retried: '.$result['retried']);
    $this->line('Resolved: '.$result['resolved']);
})->purpose('Retry failed notification deliveries.');

Schedule::command('phase7:dispatch-reminders')->dailyAt('08:00');
Schedule::command('phase7:retry-failed-notifications')->hourlyAt(15);
