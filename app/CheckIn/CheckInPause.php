<?php

declare(strict_types=1);

namespace App\CheckIn;

use App\DTOs\LocationScheduleDTO;
use App\Models\Location;
use Carbon\CarbonInterface;

/**
 * Lays a staff pause over a location's check-in schedule for the current
 * moment. Kept out of {@see CheckInScheduleResolver::resolveSchedule()},
 * which also builds future days, so a pause only ever closes "now".
 */
final readonly class CheckInPause
{
    /**
     * A paused location reads as closed with the pause reason, the way a
     * closure does, so drivers see why. Today's hours are kept.
     */
    public function apply(Location $location, LocationScheduleDTO $schedule, CarbonInterface $now): LocationScheduleDTO
    {
        if (! $location->isCheckInPaused($now)) {
            return $schedule;
        }

        return new LocationScheduleDTO(
            isOpen: false,
            openTime: $schedule->openTime,
            closeTime: $schedule->closeTime,
            reason: $location->checkins_paused_reason,
            hasOverride: true,
            isOverrideClosure: true,
        );
    }
}
