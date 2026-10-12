<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\SendAppointmentReminderAction;
use App\Appointment\ReminderSchedule;
use App\Models\Appointment;
use App\Queries\DueAppointmentReminders;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;

/**
 * Sends the day-before reminder for upcoming bookings.
 *
 * Scheduled hourly rather than once a day on purpose: locations sit in
 * different timezones, so "5pm the day before" happens at a different UTC
 * instant for each one. Running hourly also means a missed run catches up on
 * the next pass instead of dropping a day of reminders.
 *
 * Kept thin deliberately - which bookings are due lives in
 * {@see ReminderSchedule}, and what gets sent lives in
 * {@see SendAppointmentReminderAction}.
 */
#[Signature('appointments:send-reminders')]
#[Description('Send day-before reminders for upcoming appointments')]
final class SendAppointmentReminders extends Command
{
    public function handle(
        DueAppointmentReminders $candidates,
        ReminderSchedule $schedule,
        SendAppointmentReminderAction $sendReminder,
    ): int {
        $now = Date::now()->toImmutable();
        $sent = 0;

        $candidates->execute($now)
            ->filter(fn (Appointment $appointment): bool => $schedule->isDue($appointment, $now))
            ->each(function (Appointment $appointment) use ($sendReminder, &$sent): void {
                if ($sendReminder->handle($appointment)) {
                    $sent++;
                }
            });

        $this->info(sprintf('Queued %d appointment reminder(s).', $sent));

        return self::SUCCESS;
    }
}
