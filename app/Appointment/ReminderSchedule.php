<?php

declare(strict_types=1);

namespace App\Appointment;

use App\Models\Appointment;
use Carbon\CarbonImmutable;

/**
 * Decides when a booking's day-before reminder is due, and whether it still is.
 *
 * Deliberately free of both the database and the notification layer: the only
 * genuinely tricky part of reminders is the timezone arithmetic, and keeping it
 * here means it can be tested against a handful of instants without a schema.
 * {@see \App\Queries\DueAppointmentReminders} narrows the candidates in SQL;
 * this makes the actual call on each one.
 */
final readonly class ReminderSchedule
{
    /**
     * The moment the reminder should go out: the day before the appointment, at
     * the configured hour, read in the location's own timezone and handed back
     * in UTC for comparison.
     */
    public function reminderMomentFor(Appointment $appointment): CarbonImmutable
    {
        $local = $appointment->scheduled_for->setTimezone($appointment->location->timezone);

        return $local
            ->subDay()
            ->setTime($this->hour(), 0)
            ->utc();
    }

    /**
     * Whether this booking's reminder is due at the given instant.
     *
     * Three conditions, and only the first is obvious:
     *
     * 1. The reminder moment has arrived.
     * 2. The appointment has not already happened. Without this, a scheduler
     *    that was down for a day would come back and mail reminders for slots
     *    that are already in the past.
     * 3. The booking was made before its own reminder moment. A driver booking
     *    at 8pm for 6am tomorrow is already past the 5pm cutoff; reminding them
     *    would fire minutes after their booking confirmation, which reads as a
     *    bug rather than a service.
     *
     * Status, the sent stamp and the driver's preference are not checked here -
     * those are cheap and belong to the query and the send action.
     */
    public function isDue(Appointment $appointment, CarbonImmutable $now): bool
    {
        $moment = $this->reminderMomentFor($appointment);

        return $moment->lessThanOrEqualTo($now)
            && $appointment->scheduled_for->greaterThan($now)
            && $appointment->created_at->lessThanOrEqualTo($moment);
    }

    private function hour(): int
    {
        return (int) config('app.appointment_reminder_hour');
    }
}
