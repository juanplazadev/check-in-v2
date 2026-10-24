<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Appointment\AppointmentPause;
use App\Appointment\AppointmentScheduleResolver;
use App\CheckIn\CheckInPause;
use App\CheckIn\CheckInScheduleResolver;
use App\DTOs\LocationScheduleDTO;
use App\Enums\Role;
use App\Models\AppointmentSchedule;
use App\Models\AppointmentScheduleOverride;
use App\Models\CheckInSchedule;
use App\Models\CheckInScheduleOverride;
use App\Models\Location;
use App\Models\RecordHistory;
use App\Models\User;
use App\Queries\LocationStaff;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     * Past closures shown under the upcoming ones; older ones are not useful.
     */
    private const int PAST_CLOSURES_LIMIT = 10;

    /**
     * Upcoming closures teased on the overview.
     */
    private const int OVERVIEW_CLOSURES_LIMIT = 3;

    /**
     * Newest history entries shown on the History section.
     */
    private const int HISTORY_LIMIT = 200;

    public function __construct(
        private CheckInScheduleResolver $checkInSchedule,
        private CheckInPause $checkInPause,
        private AppointmentScheduleResolver $appointmentSchedule,
        private AppointmentPause $appointmentPause,
        private LocationStaff $staff,
        private RecordHistoryEntryResolver $history,
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
     * The location's staff edits and attachments, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function history(Location $location): array
    {
        return array_values($location->history()
            ->with('user')
            ->limit(self::HISTORY_LIMIT)
            ->get()
            ->map(fn (RecordHistory $entry): array => $this->history->entry($entry, 'Location added'))
            ->all());
    }

    /**
     * @return array{attached: list<array<string, mixed>>, allLocations: list<array<string, mixed>>}
     */
    public function users(Location $location): array
    {
        return [
            'attached' => $this->staffMembers($this->staff->attached($location)),
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
        ];
    }

    /**
     * Both override tables merged by date, split around today in the
     * location's timezone: every upcoming closure, soonest first, and the most
     * recent past ones, latest first.
     *
     * @return array{upcoming: list<array<string, mixed>>, past: list<array<string, mixed>>}
     */
    public function closures(Location $location, CarbonInterface $now): array
    {
        $today = $now->setTimezone($location->timezone)->toDateString();

        $closures = $this->mergeClosures(
            $location->checkinScheduleOverrides()->get(),
            $location->appointmentScheduleOverrides()->get(),
        );

        $upcoming = array_values(array_filter($closures, fn (array $closure): bool => $closure['date'] >= $today));
        $past = array_reverse(array_values(array_filter($closures, fn (array $closure): bool => $closure['date'] < $today)));

        return [
            'upcoming' => $upcoming,
            'past' => array_slice($past, 0, self::PAST_CLOSURES_LIMIT),
        ];
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
