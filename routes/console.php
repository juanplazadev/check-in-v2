<?php

declare(strict_types=1);

use App\Dashboard\ExportFiles;
use Illuminate\Foundation\Inspiring;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Populates Horizon's metrics dashboard (throughput / wait times).
Schedule::command('horizon:snapshot')->everyFiveMinutes();

// Hourly, not daily: "5pm the day before" lands on a different UTC instant for
// each location's timezone, and an hourly sweep also catches up after a missed
// run instead of dropping a day of reminders. Idempotent either way - the
// reminder_sent_at stamp is what actually prevents a second send.
Schedule::command('appointments:send-reminders')->hourly()->withoutOverlapping();

// The dashboard notification bell only ever shows the latest few, so anything
// past a month is dead weight: one row per staff member per check-in adds up.
Schedule::call(fn (): int => DatabaseNotification::query()
    ->where('created_at', '<', now()->subDays(30))
    ->delete())->daily()->name('notifications:prune');

// Spreadsheet exports are deleted as soon as they are emailed; this only sweeps up
// a file left by a worker killed mid-export.
Schedule::call(fn (): int => resolve(ExportFiles::class)->prune(now()))
    ->daily()
    ->name('exports:prune');
