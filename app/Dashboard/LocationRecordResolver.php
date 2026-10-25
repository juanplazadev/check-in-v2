<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Appointment\AppointmentPause;
use App\Appointment\AppointmentScheduleResolver;
use App\CheckIn\CheckInPause;
use App\CheckIn\CheckInScheduleResolver;
use App\DTOs\LocationScheduleDTO;
use App\Enums\AppointmentBookingSetting;
use App\Enums\AppointmentStatus;
use App\Enums\Role;
use App\Models\Appointment;
use App\Models\AppointmentSchedule;
use App\Models\AppointmentScheduleOverride;
use App\Models\CheckInSchedule;
use App\Models\CheckInScheduleOverride;
use App\Models\Location;
use App\Models\RecordHistory;
use App\Models\User;
use App\Queries\LocationStaff;
use App\Schedule\UsFederalHolidays;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Shapes a location into the contracts in resources/js/components/dashboard/types.ts:
 * DashboardLocationSummary for the locations table and the header of every
 * location section, plus one payload per section. Mapped by hand because id
 * is hidden on the model.
 *
 * Expects the users_count column that {@see \App\Queries\LocationDirectory} selects.
 */
final readonly class LocationRecordResolver
{
    /**
     * Everything {@see self::summary()} reads, for eager loading at the call site.
     *
     * @var list<string>
     */
    public const array RELATIONS = ['address'];

    /**
     * History entries per page of the History section.
     */
    public const int HISTORY_PER_PAGE = 25;

    /**
     * How far back past closures, and how far each way holidays, reach on the
     * closures page; the calendar does not go further back than this.
     */
    public const int CLOSURES_MONTHS = 12;

    /**
     * Upcoming closures teased on the overview.
     */
    private const int OVERVIEW_CLOSURES_LIMIT = 3;

    public function __construct(
        private CheckInScheduleResolver $checkInSchedule,
        private CheckInPause $checkInPause,
        private AppointmentScheduleResolver $appointmentSchedule,
        private AppointmentPause $appointmentPause,
        private LocationStaff $staff,
        private RecordHistoryEntryResolver $history,
        private UsFederalHolidays $holidays,
    ) {}

    /**
     * A row of the deleted locations list on the locations table.
     *
     * @return array{uuid: string, name: string, abbreviation: string, city: string, state: string, deletedAt: string|null}
     */
    public function deleted(Location $location): array
    {
        return [
            'uuid' => $location->uuid,
            'name' => $location->name,
            'abbreviation' => $location->abbreviation,
            'city' => $location->address->city,
            'state' => $location->address->state,
            'deletedAt' => $location->deleted_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(Location $location): array
    {
        return [
            'uuid' => $location->uuid,
            'name' => $location->name,
            'abbreviation' => $location->abbreviation,
            'city' => $location->address->city,
            'state' => $location->address->state,
            'timezone' => $location->timezone,
            'phone' => $location->phone,
            'phoneExt' => $location->phone_ext,
            'isActive' => $location->is_active,
            'isCheckInsEnabled' => $location->is_checkins_enabled,
            'isAppointmentsEnabled' => $location->is_appointments_enabled,
            'staffCount' => (int) $location->getAttribute('users_count'),
        ];
    }

    /**
     * Today's status for both features, the next few closures and where the
     * location sits on the map.
     *
     * @return array<string, mixed>
     */
    public function overview(Location $location, CarbonInterface $now): array
    {
        $localToday = $now->setTimezone($location->timezone)->startOfDay();
        $upcoming = fn (HasMany $query): HasMany => $query->whereDate('override_date', '>=', $localToday->toDateString());

        $location->load([
            'checkinSchedule',
            'appointmentSchedule',
            'checkinScheduleOverrides' => $upcoming,
            'appointmentScheduleOverrides' => $upcoming,
        ]);

        return [
            'checkIns' => [
                ...$this->today($this->checkInPause->apply($location, $this->checkInSchedule->resolveSchedule($location, $now), $now)),
                'pause' => $this->pause($location, $now),
            ],
            'appointments' => [
                ...$this->today($this->appointmentPause->apply($location, $this->appointmentSchedule->resolveSchedule($location, $now), $now)),
                'pause' => $this->appointmentPause($location, $now),
            ],
            'upcomingClosures' => array_slice(
                $this->mergeClosures($location->checkinScheduleOverrides, $location->appointmentScheduleOverrides),
                0,
                self::OVERVIEW_CLOSURES_LIMIT,
            ),
            'map' => $this->map($location),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function details(Location $location): array
    {
        $address = $location->address;

        return [
            'email' => $location->email,
            'address' => [
                'street1' => $address->street1,
                'street2' => $address->street2,
                'city' => $address->city,
                'state' => $address->state,
                'zipCode' => $address->zip_code,
                'zipCode4' => $address->zip_code_4,
                'country' => $address->country,
            ],
            'map' => $this->map($location),
            'createdAt' => $location->created_at->toIso8601String(),
        ];
    }

    /**
     * One page of the location's staff edits and attachments, newest first.
     *
     * @return LengthAwarePaginator<int, array{id: int, event: string, description: string, changes: list<array{field: string, label: string, from: string|null, to: string|null, redacted: bool}>|null, actor: string|null, createdAt: string}>
     */
    public function history(Location $location): LengthAwarePaginator
    {
        return $location->history()
            ->with('user')
            ->paginate(self::HISTORY_PER_PAGE)
            ->through(fn (RecordHistory $entry): array => $this->history->entry($entry, 'Location added'));
    }

    /**
     * @return array{attached: LengthAwarePaginator<int, array{uuid: string, name: string, email: string, roles: list<string>}>, allLocations: list<array<string, mixed>>}
     */
    public function users(Location $location, ?string $search = null): array
    {
        return [
            'attached' => $this->staff->attached($location, $search)->through($this->staffMember(...)),
            'allLocations' => $this->staffMembers($this->staff->allLocations()),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function attachableUsers(Location $location): array
    {
        return $this->staffMembers($this->staff->attachable($location));
    }

    /**
     * Staff the add location wizard can add to the new location.
     *
     * @return list<array{uuid: string, name: string, email: string, roles: list<string>}>
     */
    public function assignableUsers(): array
    {
        return $this->staffMembers($this->staff->assignable());
    }

    /**
     * @return list<array{dayOfWeek: int, openTime: string|null, closeTime: string|null}>
     */
    public function checkInHours(Location $location): array
    {
        return $this->week($location->checkinSchedule()->get());
    }

    /**
     * @return list<array{dayOfWeek: int, openTime: string|null, closeTime: string|null}>
     */
    public function appointmentHours(Location $location): array
    {
        return $this->week($location->appointmentSchedule()->get());
    }

    /**
     * Upcoming booked appointments, for the hours editor to flag the ones new
     * hours would leave out. Dates with an appointment closure or special
     * hours are left out, since those dates do not follow the weekly hours.
     *
     * @return list<array{uuid: string, referenceNumber: string, driversName: string, date: string, time: string, dayOfWeek: int}>
     */
    public function bookedAppointments(Location $location, CarbonInterface $now): array
    {
        $overrideDates = $location->appointmentScheduleOverrides()
            ->whereDate('override_date', '>=', $now->setTimezone($location->timezone)->toDateString())
            ->get()
            ->map(fn (AppointmentScheduleOverride $override): string => $override->override_date->toDateString())
            ->all();

        return array_values(Appointment::query()
            ->where('location_id', $location->id)
            ->where('status', AppointmentStatus::Scheduled)
            ->where('scheduled_for', '>=', $now)
            ->orderBy('scheduled_for')
            ->get(['uuid', 'reference_number', 'drivers_name', 'scheduled_for'])
            ->map(fn (Appointment $appointment): array => [
                'uuid' => $appointment->uuid,
                'referenceNumber' => $appointment->reference_number,
                'driversName' => $appointment->drivers_name,
                'date' => $appointment->scheduled_for->setTimezone($location->timezone)->toDateString(),
                'time' => $appointment->scheduled_for->setTimezone($location->timezone)->format('H:i'),
                'dayOfWeek' => $appointment->scheduled_for->setTimezone($location->timezone)->dayOfWeek,
            ])
            ->reject(fn (array $appointment): bool => in_array($appointment['date'], $overrideDates, true))
            ->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function checkInOptions(Location $location): array
    {
        return [
            'formFields' => $location->checkInFormFields(),
            'notificationEmails' => $location->checkInEmailAddresses(),
            'maxDistanceAllowed' => $location->max_distance_allowed,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function appointmentOptions(Location $location): array
    {
        return [
            'booking' => [
                'maxDaysAhead' => $location->appointmentMaxBookingDaysAhead(),
                'slotIntervalMinutes' => $location->appointmentSlotIntervalMinutes(),
                'minLeadTimeMinutes' => $location->appointmentMinLeadTimeMinutes(),
                'bufferBeforeCloseMinutes' => $location->appointmentBufferBeforeCloseMinutes(),
            ],
            'notificationEmails' => $location->appointmentEmailAddresses(),
            // What the booking rules may be set to; the update request enforces the same.
            'limits' => [
                'maxDaysAhead' => (int) config('app.appointment_booking.max_days_ahead'),
                'slotIntervalMinutes' => array_map(intval(...), config('app.appointment_booking.slot_interval_minutes.allowed')),
                'maxLeadTimeMinutes' => (int) config('app.appointment_booking.lead_time_minutes.max'),
                'maxBufferBeforeCloseMinutes' => (int) config('app.appointment_booking.buffer_before_close_minutes.max'),
                'minuteStep' => AppointmentBookingSetting::MINUTE_STEP,
            ],
        ];
    }

    /**
     * Both override tables merged by date, split around today in the
     * location's timezone: every upcoming closure, soonest first, and the past
     * year's, latest first. Also US federal holidays a year each way, and,
     * for those who may add closures, how many appointments are booked on
     * each upcoming date, so the add dialog can warn about them.
     *
     * @return array{today: string, upcoming: list<array<string, mixed>>, past: list<array<string, mixed>>, holidays: list<array{date: string, name: string}>, bookedAppointments: array<string, int>}
     */
    public function closures(Location $location, CarbonInterface $now, bool $withBookings): array
    {
        $localToday = $now->setTimezone($location->timezone)->startOfDay();
        $today = $localToday->toDateString();
        $since = $localToday->subMonthsNoOverflow(self::CLOSURES_MONTHS)->toDateString();

        $closures = $this->mergeClosures(
            $location->checkinScheduleOverrides()->whereDate('override_date', '>=', $since)->get(),
            $location->appointmentScheduleOverrides()->whereDate('override_date', '>=', $since)->get(),
        );

        return [
            'today' => $today,
            'upcoming' => array_values(array_filter($closures, fn (array $closure): bool => $closure['date'] >= $today)),
            'past' => array_reverse(array_values(array_filter($closures, fn (array $closure): bool => $closure['date'] < $today))),
            'holidays' => $this->holidays->between(
                $localToday->subMonthsNoOverflow(self::CLOSURES_MONTHS),
                $localToday->addMonthsNoOverflow(self::CLOSURES_MONTHS),
            ),
            'bookedAppointments' => $withBookings ? $this->bookedAppointmentsByDate($location, $now) : [],
        ];
    }

    /**
     * Upcoming booked appointments counted by their date in the location's
     * timezone.
     *
     * @return array<string, int>
     */
    private function bookedAppointmentsByDate(Location $location, CarbonInterface $now): array
    {
        return Appointment::query()
            ->where('location_id', $location->id)
            ->where('status', AppointmentStatus::Scheduled)
            ->where('scheduled_for', '>=', $now)
            ->pluck('scheduled_for')
            ->countBy(fn (CarbonInterface $scheduledFor): string => $scheduledFor->setTimezone($location->timezone)->toDateString())
            ->all();
    }

    /**
     * One row per date and kind of change. A holiday entered for both features
     * with the same hours and reason becomes a single row that applies to both;
     * if the two tables disagree, each keeps its own row.
     *
     * @param  Collection<int, CheckInScheduleOverride>  $checkIns
     * @param  Collection<int, AppointmentScheduleOverride>  $appointments
     * @return list<array{date: string, isClosed: bool, openTime: string|null, closeTime: string|null, reason: string, appliesTo: list<string>}>
     */
    private function mergeClosures(Collection $checkIns, Collection $appointments): array
    {
        $rows = [];

        foreach (['checkIns' => $checkIns, 'appointments' => $appointments] as $feature => $overrides) {
            foreach ($overrides as $override) {
                $row = [
                    'date' => $override->override_date->toDateString(),
                    'isClosed' => $override->is_closed,
                    'openTime' => $override->is_closed ? null : $this->time($override->open_time),
                    'closeTime' => $override->is_closed ? null : $this->time($override->close_time),
                    'reason' => $override->reason,
                ];

                $key = implode('|', array_map(strval(...), $row));
                $rows[$key] ??= [...$row, 'appliesTo' => []];
                $rows[$key]['appliesTo'][] = $feature;
            }
        }

        $rows = array_values($rows);
        usort($rows, fn (array $a, array $b): int => [$a['date'], $a['appliesTo']] <=> [$b['date'], $b['appliesTo']]);

        return $rows;
    }

    /**
     * Monday first, as staff read a work week. A day without a row is closed.
     *
     * @param  Collection<int, CheckInSchedule>|Collection<int, AppointmentSchedule>  $schedules
     * @return list<array{dayOfWeek: int, openTime: string|null, closeTime: string|null}>
     */
    private function week(Collection $schedules): array
    {
        $byDay = $schedules->keyBy('day_of_week');

        return array_map(function (int $day) use ($byDay): array {
            $schedule = $byDay->get($day);

            return [
                'dayOfWeek' => $day,
                'openTime' => $schedule === null ? null : $this->time($schedule->open_time),
                'closeTime' => $schedule === null ? null : $this->time($schedule->close_time),
            ];
        }, [1, 2, 3, 4, 5, 6, 0]);
    }

    /**
     * @return array{isOpen: bool, openTime: string|null, closeTime: string|null, hasOverride: bool, reason: string|null}
     */
    private function today(LocationScheduleDTO $schedule): array
    {
        return [
            'isOpen' => $schedule->isOpen,
            'openTime' => $schedule->openTime?->format('H:i'),
            'closeTime' => $schedule->closeTime?->format('H:i'),
            'hasOverride' => $schedule->hasOverride,
            'reason' => $schedule->reason,
        ];
    }

    /**
     * The active check-in pause, if any. Until is a wall-clock HH:MM in the
     * location's timezone, like the hours, or null when it lasts until resumed.
     *
     * @return array{until: string|null, reason: string|null}|null
     */
    private function pause(Location $location, CarbonInterface $now): ?array
    {
        if (! $location->isCheckInPaused($now)) {
            return null;
        }

        return [
            'until' => $location->checkins_paused_until?->setTimezone($location->timezone)->format('H:i'),
            'reason' => $location->checkins_paused_reason,
        ];
    }

    /**
     * The active booking pause, if any. Until is an ISO 8601 datetime in the
     * location's timezone, since a pause can run for days, or null when it
     * lasts until resumed.
     *
     * @return array{until: string|null, reason: string|null}|null
     */
    private function appointmentPause(Location $location, CarbonInterface $now): ?array
    {
        if (! $location->isAppointmentPaused($now)) {
            return null;
        }

        return [
            'until' => $location->appointments_paused_until?->setTimezone($location->timezone)->toIso8601String(),
            'reason' => $location->appointments_paused_reason,
        ];
    }

    /**
     * @return array{latitude: float|null, longitude: float|null, maxDistanceAllowed: int}
     */
    private function map(Location $location): array
    {
        return [
            'latitude' => $location->address->latitude,
            'longitude' => $location->address->longitude,
            'maxDistanceAllowed' => $location->max_distance_allowed,
        ];
    }

    /**
     * HH:MM from the HH:MM:SS the time columns hold.
     */
    private function time(?string $time): ?string
    {
        return $time === null ? null : mb_substr($time, 0, 5);
    }

    /**
     * @param  Collection<int, User>  $users
     * @return list<array{uuid: string, name: string, email: string, roles: list<string>}>
     */
    private function staffMembers(Collection $users): array
    {
        return array_values($users->map($this->staffMember(...))->all());
    }

    /**
     * @return array{uuid: string, name: string, email: string, roles: list<string>}
     */
    private function staffMember(User $user): array
    {
        $staffRoles = array_map(fn (Role $role): string => $role->value, Role::staff());

        return [
            'uuid' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
            // Staff roles only, as in UserRecordResolver.
            'roles' => array_values(array_intersect($staffRoles, $user->roles->pluck('name')->all())),
        ];
    }
}
