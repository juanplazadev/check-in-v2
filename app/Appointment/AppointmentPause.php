<?php

declare(strict_types=1);

namespace App\Appointment;

use App\DTOs\LocationScheduleDTO;
use App\Models\Location;
use Carbon\CarbonInterface;

/**
 * Lays a staff pause over a location's appointment schedule for the current
 * moment. Kept out of {@see AppointmentScheduleResolver::resolveSchedule()},
 * which also builds future days and the dashboard hours, the same way as
 * {@see \App\CheckIn\CheckInPause}.
 */
final readonly class AppointmentPause
{
    /**
     * A paused location reads as closed with the pause reason, the way a
     * closure does, so guests see why. Today's hours are kept.
     */
    public function apply(Location $location, LocationScheduleDTO $schedule, CarbonInterface $now): LocationScheduleDTO
    {
        if (! $location->isAppointmentPaused($now)) {
            return $schedule;
        }

        return new LocationScheduleDTO(
            isOpen: false,
            openTime: $schedule->openTime,
            closeTime: $schedule->closeTime,
            reason: $location->appointments_paused_reason,
            hasOverride: true,
            isOverrideClosure: true,
        );
    }
}
