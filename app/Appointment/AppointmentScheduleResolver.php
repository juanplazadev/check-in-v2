<?php

declare(strict_types=1);

namespace App\Appointment;

use App\Address\AddressManager;
use App\DTOs\AppointmentAvailabilityWindowDTO;
use App\DTOs\AppointmentLocationDTO;
use App\DTOs\LocationScheduleDTO;
use App\Models\Location;
use App\Queries\AppointmentLocations;
use App\Schedule\LocationScheduleParser;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

final readonly class AppointmentScheduleResolver
{
    public function __construct(
        private AppointmentLocations $locationSchedules,
        private LocationScheduleParser $locationScheduleParser,
        private AppointmentPause $pause,
        private AddressManager $address,
    ) {}

    /**
     * @return Collection<int, AppointmentLocationDTO>
     */
    public function getLocations(): Collection
    {
        $now = now();

        return $this->locationSchedules->execute()
            ->map(fn (Location $location): AppointmentLocationDTO => $this->buildDTO($location, $now));
    }

    public function buildDTO(Location $location, CarbonInterface $now): AppointmentLocationDTO
    {
        $localNow = $now->setTimezone($location->timezone);
        $schedule = $this->pause->apply($location, $this->resolveSchedule($location, $localNow), $localNow);
        $interval = $location->appointmentSlotIntervalMinutes();

        return new AppointmentLocationDTO(
            id: $location->uuid,
            name: $location->name,
            address: $this->address->buildAddress($location->address->toArray()),
            isOpen: $schedule->isOpen,
            todayOpenCloseTime: $this->formatOpenCloseTime($schedule),
            reason: $this->resolveReason($schedule),
            hasOverride: $schedule->hasOverride,
            isOverrideClosure: $schedule->isOverrideClosure,
            isClosingSoon: $this->isClosingSoon($schedule, $localNow),
            availability: $this->resolveAvailabilityWindows(
                $location,
                $localNow,
                $location->appointmentMaxBookingDaysAhead(),
                $interval,
                $location->appointmentMinLeadTimeMinutes(),
                $location->appointmentBufferBeforeCloseMinutes(),
            ),
            slotIntervalMinutes: $interval,
        );
    }

    public function isBookableSlot(Location $location, CarbonInterface $candidate): bool
    {
        $localNow = Date::now($location->timezone);
        $localCandidate = $candidate->copy()->setTimezone($location->timezone);
        $interval = $location->appointmentSlotIntervalMinutes();

        $windows = $this->resolveAvailabilityWindows(
            $location,
            $localNow,
            $location->appointmentMaxBookingDaysAhead(),
            $interval,
            $location->appointmentMinLeadTimeMinutes(),
            $location->appointmentBufferBeforeCloseMinutes(),
        );

        foreach ($windows as $window) {
            if ($window->date !== $localCandidate->toDateString()) {
                continue;
            }

            $first = Date::parse(sprintf('%s %s', $window->date, $window->firstSlot), $location->timezone);
            $last = Date::parse(sprintf('%s %s', $window->date, $window->lastSlot), $location->timezone);

            if (! $localCandidate->betweenIncluded($first, $last)) {
                return false;
            }

            $steps = (int) $first->diffInMinutes($localCandidate);

            return $steps % $interval === 0
                && $first->copy()->addMinutes($steps)->equalTo($localCandidate);
        }

        return false;
    }

    public function resolveSchedule(Location $location, CarbonInterface $at): LocationScheduleDTO
    {

        $localNow = $at->setTimezone($location->timezone);

        $override = $location->appointmentScheduleOverrides
            ->first(fn ($o): bool => $o->override_date->isSameDay($localNow));

        $schedule = $location->appointmentSchedule
            ->first(fn ($s): bool => $s->day_of_week === $localNow->dayOfWeek());

        if ($override) {
            return $this->locationScheduleParser->resolveFromOverride($override->toArray(), $localNow);
        }

        return $this->locationScheduleParser->resolveFromSchedule($schedule?->toArray(), $localNow);
    }

    /**
     * @return list<AppointmentAvailabilityWindowDTO>
     */
    private function resolveAvailabilityWindows(
        Location $location,
        CarbonInterface $localNow,
        int $maxDaysAhead,
        int $intervalMinutes,
        int $leadTimeMinutes,
        int $bufferBeforeCloseMinutes,
    ): array {
        // A pause stops every new booking, on any day, until it ends.
        if ($location->isAppointmentPaused($localNow)) {
            return [];
        }

        $windows = [];

        for ($offset = 0; $offset <= $maxDaysAhead; $offset++) {
            $day = $localNow->copy()->addDays($offset)->startOfDay();
            $schedule = $this->resolveSchedule($location, $day);
            if (! $schedule->openTime instanceof CarbonInterface) {
                continue;
            }

            if (! $schedule->closeTime instanceof CarbonInterface) {
                continue;
            }

            if ($schedule->isOverrideClosure) {
                continue;
            }

            // Re-anchor parsed time-of-day onto the target day (parser anchors to "today").
            $open = $day->copy()->setTimeFrom($schedule->openTime);
            $close = $day->copy()->setTimeFrom($schedule->closeTime);

            $lastSlot = $close->copy()->subMinutes($bufferBeforeCloseMinutes);
            $firstSlot = $this->ceilToInterval($open, $intervalMinutes);

            if ($offset === 0) {
                // Earliest bookable today must respect the lead-time buffer from now.
                $earliest = $this->ceilToInterval($localNow->copy()->addMinutes($leadTimeMinutes), $intervalMinutes);

                if ($earliest->gt($firstSlot)) {
                    $firstSlot = $earliest;
                }
            }

            if ($firstSlot->gt($lastSlot)) {
                continue;
            }

            $windows[] = new AppointmentAvailabilityWindowDTO(
                date: $day->toDateString(),
                firstSlot: $firstSlot->format('H:i:s'),
                lastSlot: $lastSlot->format('H:i:s'),
            );
        }

        return $windows;
    }

    private function ceilToInterval(CarbonInterface $at, int $intervalMinutes): CarbonInterface
    {
        if ($intervalMinutes <= 0) {
            return $at->copy();
        }

        $startOfDay = $at->copy()->startOfDay();
        $elapsedMinutes = $startOfDay->diffInMinutes($at);
        $steps = (int) ceil($elapsedMinutes / $intervalMinutes);

        return $startOfDay->addMinutes($steps * $intervalMinutes);
    }

    private function formatOpenCloseTime(LocationScheduleDTO $schedule): string
    {
        return match (true) {
            $schedule->openTime instanceof CarbonInterface && $schedule->closeTime instanceof CarbonInterface => $schedule->openTime->format('g:i A').' - '.$schedule->closeTime->format('g:i A T'),
            ! $schedule->isOpen => __('messages.appointmentSelectLocation.closedToday'),
            default => 'N/A',
        };
    }

    private function resolveReason(LocationScheduleDTO $schedule): ?string
    {
        if (! $schedule->hasOverride) {
            return null;
        }

        if ($schedule->isOverrideClosure) {
            return $schedule->reason ?? __('messages.appointmentSelectLocation.closedToday');
        }

        return $schedule->reason ?? __('messages.appointmentSelectLocation.specialHoursToday');
    }

    private function isClosingSoon(LocationScheduleDTO $schedule, CarbonInterface $localNow): bool
    {
        return $schedule->isOpen
            && $schedule->closeTime instanceof CarbonInterface
            && $localNow->diffInMinutes($schedule->closeTime) <= config('app.location_closing_soon_threshold');
    }
}
