<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\CarbonInterface;

/**
 * How long staff pause new bookings at a location. Day-based, since bookings
 * are made days ahead. Mirrored as AppointmentPauseDuration in
 * resources/js/components/dashboard/types.ts.
 */
enum AppointmentPauseDuration: string
{
    case RestOfToday = 'rest-of-today';
    case OneDay = '1d';
    case ThreeDays = '3d';
    case OneWeek = '1w';
    case UntilResumed = 'until-resumed';

    /**
     * When the pause ends, or null when it lasts until someone resumes it.
     * Rest of today ends at the next midnight in the given time's timezone,
     * so pass the location's local now.
     */
    public function endsAt(CarbonInterface $now): ?CarbonInterface
    {
        return match ($this) {
            self::RestOfToday => $now->addDay()->startOfDay(),
            self::OneDay => $now->addDay(),
            self::ThreeDays => $now->addDays(3),
            self::OneWeek => $now->addWeek(),
            self::UntilResumed => null,
        };
    }
}
