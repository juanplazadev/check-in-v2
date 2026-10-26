<?php

declare(strict_types=1);

namespace App\Schedule;

use App\Address\AddressManager;
use App\Appointment\AppointmentPause;
use App\Appointment\AppointmentScheduleResolver;
use App\CheckIn\CheckInPause;
use App\CheckIn\CheckInScheduleResolver;
use App\DTOs\LocationScheduleDTO;
use App\DTOs\LocationWeekDTO;
use App\DTOs\ScheduleDayDTO;
use App\Models\Location;
use App\Queries\ScheduleLocations;
use Carbon\CarbonInterface;
use Carbon\Constants\UnitValue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final readonly class WeeklyScheduleResolver
{
    private const string CACHE_KEY_PREFIX = 'schedule:weeks:';

    public function __construct(
        private ScheduleLocations $locations,
        private CheckInScheduleResolver $checkInResolver,
        private CheckInPause $checkInPause,
        private AppointmentPause $appointmentPause,
        private AppointmentScheduleResolver $appointmentResolver,
        private AddressManager $address,
    ) {}

    /**
     * Forget every locale's cached schedule so the next request rebuilds it.
     */
    public static function flushCache(): void
    {
        foreach (config('app.locales') as $locale) {
            Cache::forget(self::CACHE_KEY_PREFIX.$locale);
        }
    }

    /**
     * Cached per locale (weekday names are localized). The short TTL bounds how
     * stale the live "open now" badge and the "today" highlight can get, while
     * schedule edits flush the cache immediately via {@see self::flushCache()}.
     *
     * @return Collection<int, LocationWeekDTO>
     */
    public function getWeeks(): Collection
    {
        return Cache::remember(
            self::CACHE_KEY_PREFIX.app()->getLocale(),
            now()->addMinutes(config('app.schedule_cache_ttl')),
            fn (): Collection => $this->buildWeeks(),
        );
    }

    public function buildWeek(Location $location, CarbonInterface $now): LocationWeekDTO
    {
        $localNow = $now->copy()->setTimezone($location->timezone);
        $startOfWeek = $localNow->startOfWeek(UnitValue::MONDAY);

        $checkInWeek = [];
        $appointmentWeek = [];

        for ($offset = 0; $offset < 7; $offset++) {
            $day = $startOfWeek->addDays($offset);
            $checkInWeek[] = $this->buildDay($this->checkInResolver->resolveSchedule($location, $day->setTime(12, 0)), $day, $localNow);
            $appointmentWeek[] = $this->buildDay($this->appointmentResolver->resolveSchedule($location, $day->setTime(12, 0)), $day, $localNow);
        }

        return new LocationWeekDTO(
            id: $location->uuid,
            name: $location->name,
            address: $this->address->buildAddress($location->address->toArray()),
            timezoneAbbr: $localNow->format('T'),
            checkInEnabled: $location->is_checkins_enabled,
            appointmentEnabled: $location->is_appointments_enabled,
            isOpenNowCheckIn: $location->is_checkins_enabled && $this->checkInPause->apply($location, $this->checkInResolver->resolveSchedule($location, $now), $now)->isOpen,
            isOpenNowAppointment: $location->is_appointments_enabled && $this->appointmentPause->apply($location, $this->appointmentResolver->resolveSchedule($location, $now), $now)->isOpen,
            checkInWeek: $checkInWeek,
            appointmentWeek: $appointmentWeek,
        );
    }

    /**
     * @return Collection<int, LocationWeekDTO>
     */
    private function buildWeeks(): Collection
    {
        $now = now();

        return $this->locations->execute()
            ->map(fn (Location $location): LocationWeekDTO => $this->buildWeek($location, $now));
    }

    private function buildDay(LocationScheduleDTO $schedule, CarbonInterface $day, CarbonInterface $localNow): ScheduleDayDTO
    {
        $hours = $schedule->openTime instanceof CarbonInterface && $schedule->closeTime instanceof CarbonInterface
            ? $schedule->openTime->format('g:i A').' – '.$schedule->closeTime->format('g:i A')
            : null;

        $locale = app()->getLocale();

        return new ScheduleDayDTO(
            weekday: $day->locale($locale)->translatedFormat('l'),
            shortWeekday: $day->locale($locale)->translatedFormat('D'),
            dayOfMonth: $day->day,
            isToday: $day->isSameDay($localNow),
            hours: $schedule->isOverrideClosure ? null : $hours,
            hasOverride: $schedule->hasOverride,
            isOverrideClosure: $schedule->isOverrideClosure,
            reason: $schedule->reason,
        );
    }
}
