<?php

use App\Services\BackupSettings;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('splis:backup-database')
    ->dailyAt(app(BackupSettings::class)->scheduleTime())
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/backup-schedule.log'));

Schedule::command('splis:notify-expiring-agendas')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('splis:prune-notifications')
    ->dailyAt('03:15')
    ->withoutOverlapping()
    ->onOneServer();
