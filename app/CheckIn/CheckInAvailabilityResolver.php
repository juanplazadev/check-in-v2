<?php

declare(strict_types=1);

namespace App\CheckIn;

use App\DTOs\LocationAvailabilityDTO;
use App\DTOs\LocationScheduleDTO;
use App\Geo\DistanceCalculator;
use App\Models\Location;
use Carbon\CarbonInterface;

final readonly class CheckInAvailabilityResolver
{
    public function __construct(
        private CheckInScheduleResolver $scheduleResolver,
        private DistanceCalculator $distance,
    ) {}

    /**
     * @param  array<mixed, mixed>  $userCoords
     */
    public function isAvailableForCheckIn(Location $location, array $userCoords): LocationAvailabilityDTO
    {
        $userDistance = $this->distance->calculate(
            $userCoords['latitude'],
            $userCoords['longitude'],
            $location->address->latitude ?? 0.0,
            $location->address->longitude ?? 0.0
        );

        if ($userDistance >= $location->max_distance_allowed) {
            return new LocationAvailabilityDTO(
                allowed: false,
                reason: __('messages.checkInSelectLocation.tooFar', [
                    'name' => $location->name,
                    'maxDistance' => $location->max_distance_allowed,
                    'userDistance' => $userDistance,
                ]),
            );
        }

        $localNow = now()->setTimezone($location->timezone);
        $schedule = $this->scheduleResolver->resolveSchedule($location, $localNow);

        return new LocationAvailabilityDTO(
            allowed: $schedule->isOpen,
            reason: $this->resolveReason($location, $schedule)
        );
    }

    private function resolveReason(Location $location, LocationScheduleDTO $schedule): ?string
    {
        if ($schedule->isOpen) {
            return null;
        }

        // Admin-configured reason takes priority (e.g., "Closed for maintenance")
        if ($schedule->reason) {
            return __('messages.checkInSelectLocation.closedForCheckInWithReason', [
                'name' => $location->name,
                'reason' => $schedule->reason,
            ]);
        }

        // Schedule exception exists but admin didn't provide a reason - generate one

        if ($schedule->isOverrideClosure) {
            return __('messages.checkInSelectLocation.closedForCheckInWithoutReason', [
                'name' => $location->name,
            ]);
        }

        if ($schedule->hasOverride && $schedule->openTime instanceof CarbonInterface && $schedule->closeTime instanceof CarbonInterface) {
            return __('messages.checkInSelectLocation.outsideExceptionHours', [
                'name' => $location->name,
                'open' => $schedule->openTime->format('g:i A'),
                'close' => $schedule->closeTime->format('g:i A T'),
            ]);
        }

        // No exception - outside regular business hours
        if ($schedule->openTime instanceof CarbonInterface && $schedule->closeTime instanceof CarbonInterface) {
            return __('messages.checkInSelectLocation.outsideHours', [
                'name' => $location->name,
                'open' => $schedule->openTime->format('g:i A'),
                'close' => $schedule->closeTime->format('g:i A T'),
            ]);
        }

        // No hours configured for today
        return __('messages.checkInSelectLocation.closedForCheckInToday', ['name' => $location->name]);
    }
}
