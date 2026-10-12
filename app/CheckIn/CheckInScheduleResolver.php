<?php

declare(strict_types=1);

namespace App\CheckIn;

use App\Address\AddressManager;
use App\DTOs\CheckInLocationDTO;
use App\DTOs\LocationScheduleDTO;
use App\Models\Location;
use App\Queries\CheckInLocations;
use App\Schedule\LocationScheduleParser;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final readonly class CheckInScheduleResolver
{
    public function __construct(
        private CheckInLocations $locationSchedules,
        private LocationScheduleParser $locationScheduleParser,
        private AddressManager $address,
    ) {}

    /**
     * @return Collection<int, CheckInLocationDTO>
     */
    public function getLocations(): Collection
    {
        return $this->locationSchedules->execute()
            ->map(fn (Location $location): CheckInLocationDTO => $this->buildDTO($location, now()));
    }

    public function buildDTO(Location $location, CarbonInterface $now): CheckInLocationDTO
    {
        $localNow = $now->setTimezone($location->timezone);
        $schedule = $this->resolveSchedule($location, $localNow);

        return new CheckInLocationDTO(
            id: $location->uuid,
            name: $location->name,
            address: $this->address->buildAddress($location->address->toArray()),
            maxDistanceAllowed: $location->max_distance_allowed,
            isOpen: $schedule->isOpen,
            todayOpenCloseTime: $this->formatOpenCloseTime($schedule),
            reason: $this->resolveReason($schedule),
            hasOverride: $schedule->hasOverride,
            isOverrideClosure: $schedule->isOverrideClosure,
            isClosingSoon: $this->isClosingSoon($schedule, $localNow),
        );
    }

    public function resolveSchedule(Location $location, CarbonInterface $now): LocationScheduleDTO
    {
        $localNow = $now->setTimezone($location->timezone);

        $override = $location->checkinScheduleOverrides
            ->first(fn ($o): bool => $o->override_date->isSameDay($localNow));

        $schedule = $location->checkinSchedule
            ->first(fn ($s): bool => $s->day_of_week === $localNow->dayOfWeek());

        if ($override) {
            return $this->locationScheduleParser->resolveFromOverride($override->toArray(), $localNow);
        }

        return $this->locationScheduleParser->resolveFromSchedule($schedule?->toArray(), $localNow);
    }

    private function formatOpenCloseTime(LocationScheduleDTO $schedule): string
    {
        return match (true) {
            $schedule->openTime instanceof CarbonInterface && $schedule->closeTime instanceof CarbonInterface => $schedule->openTime->format('g:i A').' - '.$schedule->closeTime->format('g:i A T'),
            ! $schedule->isOpen && ! $schedule->openTime && ! $schedule->closeTime => __('messages.checkInSelectLocation.closedToday'),
            default => 'N/A',
        };
    }

    private function resolveReason(LocationScheduleDTO $schedule): ?string
    {
        if (! $schedule->hasOverride) {
            return null;
        }

        if ($schedule->isOverrideClosure) {
            return $schedule->reason ?? __('messages.checkInSelectLocation.closedToday');
        }

        return $schedule->reason ?? __('messages.checkInSelectLocation.specialHoursToday');
    }

    private function isClosingSoon(LocationScheduleDTO $schedule, CarbonInterface $localNow): bool
    {
        return $schedule->isOpen
            && $schedule->closeTime instanceof CarbonInterface
            && $localNow->diffInMinutes($schedule->closeTime) <= config('app.location_closing_soon_threshold');
    }
}
