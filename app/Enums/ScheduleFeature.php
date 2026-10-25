<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\AppointmentSchedule;
use App\Models\AppointmentScheduleOverride;
use App\Models\CheckInSchedule;
use App\Models\CheckInScheduleOverride;
use App\Models\Location;
use Illuminate\Database\Eloquent\Relations\HasMany;
use ValueError;

/**
 * The two features a location keeps weekly hours and closures for. Each has
 * its own schedule and override tables and history events, but both are
 * edited the same way.
 */
enum ScheduleFeature: string
{
    case CheckIns = 'check-ins';
    case Appointments = 'appointments';

    /**
     * The feature behind a closure's appliesTo key; the inverse of closureKey().
     */
    public static function fromClosureKey(string $key): self
    {
        foreach (self::cases() as $feature) {
            if ($feature->closureKey() === $key) {
                return $feature;
            }
        }

        throw new ValueError(sprintf('"%s" is not a closure feature key.', $key));
    }

    /**
     * @return HasMany<CheckInSchedule, Location>|HasMany<AppointmentSchedule, Location>
     */
    public function schedule(Location $location): HasMany
    {
        return match ($this) {
            self::CheckIns => $location->checkinSchedule(),
            self::Appointments => $location->appointmentSchedule(),
        };
    }

    /**
     * The dated closures and special hours that override the weekly hours.
     *
     * @return HasMany<CheckInScheduleOverride, Location>|HasMany<AppointmentScheduleOverride, Location>
     */
    public function overrides(Location $location): HasMany
    {
        return match ($this) {
            self::CheckIns => $location->checkinScheduleOverrides(),
            self::Appointments => $location->appointmentScheduleOverrides(),
        };
    }

    /**
     * The feature's key in a closure's appliesTo, as the dashboard sends it.
     */
    public function closureKey(): string
    {
        return match ($this) {
            self::CheckIns => 'checkIns',
            self::Appointments => 'appointments',
        };
    }

    public function historyEvent(): RecordHistoryEvent
    {
        return match ($this) {
            self::CheckIns => RecordHistoryEvent::CheckInHoursEdited,
            self::Appointments => RecordHistoryEvent::AppointmentHoursEdited,
        };
    }

    public function userHistoryEvent(): UserHistoryEvent
    {
        return match ($this) {
            self::CheckIns => UserHistoryEvent::LocationCheckInHoursEdited,
            self::Appointments => UserHistoryEvent::LocationAppointmentHoursEdited,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::CheckIns => 'Check-in',
            self::Appointments => 'Appointment',
        };
    }
}
