<?php

declare(strict_types=1);

namespace App\Queries;

use App\Dashboard\AppointmentRecordResolver;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Illuminate\Database\Eloquent\Collection;

/**
 * Scheduled appointments for the live queue's arriving rail, soonest first.
 *
 * A rolling window rather than "today": every location keeps its own timezone,
 * so there is no single today. Late bookings stay for a grace period, then
 * drop off until someone marks them as a no-show.
 */
final readonly class LiveQueueAppointments
{
    public const int LATE_GRACE_HOURS = 4;

    public const int AHEAD_HOURS = 24;

    /** A ceiling on the payload, far above any real day's bookings. */
    public const int MAX_RECORDS = 200;

    /**
     * @return Collection<int, Appointment>
     */
    public function execute(): Collection
    {
        return Appointment::query()
            ->where('status', AppointmentStatus::Scheduled)
            ->whereBetween('scheduled_for', [
                now()->subHours(self::LATE_GRACE_HOURS),
                now()->addHours(self::AHEAD_HOURS),
            ])
            ->with(AppointmentRecordResolver::RELATIONS)
            ->orderBy('scheduled_for')
            ->orderBy('id')
            ->limit(self::MAX_RECORDS)
            ->get();
    }
}
