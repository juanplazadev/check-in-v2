<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\CarbonInterface;

/**
 * How long staff pause new check-ins at a location. Mirrored as
 * CheckInPauseDuration in resources/js/components/dashboard/types.ts.
 */
enum CheckInPauseDuration: string
{
    case ThirtyMinutes = '30m';
    case OneHour = '1h';
    case TwoHours = '2h';
    case RestOfToday = 'rest-of-today';
    case UntilResumed = 'until-resumed';

    /**
     * When the pause ends, or null when it lasts until someone resumes it.
     * Rest of today ends at today's check-in closing time, which the caller
     * must already know is still ahead.
     */
    public function endsAt(CarbonInterface $now, ?CarbonInterface $todayCloseTime): ?CarbonInterface
    {
        return match ($this) {
            self::ThirtyMinutes => $now->addMinutes(30),
            self::OneHour => $now->addHour(),
            self::TwoHours => $now->addHours(2),
            self::RestOfToday => $todayCloseTime,
            self::UntilResumed => null,
        };
    }
}
