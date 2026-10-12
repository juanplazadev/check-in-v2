<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

final readonly class DueAppointmentReminders
{
    /**
     * How far ahead to look for bookings that might be due a reminder.
     *
     * The reminder moment is at most ~31 hours before a slot (a 23:59 booking
     * reminded at 17:00 the day before), so 48 leaves room without widening the
     * scan. Deliberately a coarse window: the per-location timezone arithmetic
     * that decides the actual moment lives in
     * {@see \App\Appointment\ReminderSchedule} rather than in SQL, which keeps
     * it testable and portable instead of pgsql-specific.
     */
    public const int WINDOW_HOURS = 48;

    /**
     * Bookings that could be due a reminder now. Callers must still put each
     * one past ReminderSchedule::isDue() and the driver's own preference.
     *
     * @return Collection<int, Appointment>
     */
    public function execute(CarbonImmutable $now): Collection
    {
        return Appointment::query()
            ->with(['location', 'user'])
            ->where('status', AppointmentStatus::Scheduled)
            ->whereNull('reminder_sent_at')
            ->whereNotNull('user_id')
            ->whereBetween('scheduled_for', [$now, $now->addHours(self::WINDOW_HOURS)])
            ->orderBy('scheduled_for')
            ->get();
    }
}
