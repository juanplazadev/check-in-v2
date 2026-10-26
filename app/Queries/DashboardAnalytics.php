<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\AnalyticsPeriod;
use App\Enums\AppointmentStatus;
use App\Enums\CheckInStatus;
use App\Models\Appointment;
use App\Models\CheckIn;
use App\Models\Location;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The staff analytics page: check-in and appointment volume, outcomes, per-location ranking
 * and busy hours, for one preset period and the period before it.
 *
 * Check-ins count by when they were submitted and appointments by when they are scheduled,
 * archived ones included. Every figure is bucketed in the viewer's timezone. That date math
 * differs between Postgres and SQLite, so instead of grouping in SQL this streams the few
 * columns it needs and buckets them in PHP: one pass over each table per visit.
 *
 * Rates:
 * - completion rate is completed check-ins over all check-ins submitted in the period;
 * - turnaround is the median minutes from submission to completion, completed check-ins only;
 * - no-show rate is no-shows over appointments that were due and resolved (no-show, checked
 *   in or completed), so scheduled and canceled ones do not dilute it.
 */
final readonly class DashboardAnalytics
{
    /**
     * @param  array{period: AnalyticsPeriod, locations: list<int>, timezone: string}  $filters
     * @return array{
     *     summary: array{
     *         checkIns: array{value: int, previous: int},
     *         completionRate: array{value: float|null, previous: float|null},
     *         medianTurnaround: array{value: int|null, previous: int|null},
     *         appointments: array{value: int, previous: int},
     *         noShowRate: array{value: float|null, previous: float|null},
     *     },
     *     volume: array{bucket: string, labels: list<string>, checkIns: list<int>, appointments: list<int>},
     *     statuses: array{checkIns: array<string, int>, appointments: array<string, int>},
     *     ranking: list<array{id: int, name: string, abbreviation: string, checkIns: int, completionRate: float|null, medianTurnaround: int|null, appointments: int, noShowRate: float|null}>,
     *     timeOfDay: array{hourly: list<int>, weekdays: list<int>},
     * }
     */
    public function execute(User $user, array $filters): array
    {
        $period = $filters['period'];
        $timezone = $filters['timezone'];
        $bounds = $period->bounds($timezone);
        $buckets = array_fill_keys(array_map($period->bucketKey(...), $period->buckets($timezone)), 0);

        $checkIns = $this->checkIns($user, $filters, $bounds, $buckets);
        $appointments = $this->appointments($user, $filters, $bounds, $buckets);

        return [
            'summary' => [
                'checkIns' => [
                    'value' => $checkIns['current']['total'],
                    'previous' => $checkIns['previous']['total'],
                ],
                'completionRate' => [
                    'value' => $this->rate($checkIns['current']['completed'], $checkIns['current']['total']),
                    'previous' => $this->rate($checkIns['previous']['completed'], $checkIns['previous']['total']),
                ],
                'medianTurnaround' => [
                    'value' => $this->median($checkIns['current']['turnarounds']),
                    'previous' => $this->median($checkIns['previous']['turnarounds']),
                ],
                'appointments' => [
                    'value' => $appointments['current']['total'],
                    'previous' => $appointments['previous']['total'],
                ],
                'noShowRate' => [
                    'value' => $this->rate($appointments['current']['noShows'], $appointments['current']['due']),
                    'previous' => $this->rate($appointments['previous']['noShows'], $appointments['previous']['due']),
                ],
            ],
            'volume' => [
                'bucket' => $period->bucket(),
                'labels' => array_keys($buckets),
                'checkIns' => array_values($checkIns['volume']),
                'appointments' => array_values($appointments['volume']),
            ],
            'statuses' => [
                'checkIns' => $checkIns['statuses'],
                'appointments' => $appointments['statuses'],
            ],
            'ranking' => $this->ranking($user, $filters, $checkIns['locations'], $appointments['locations']),
            'timeOfDay' => [
                'hourly' => array_values($checkIns['hourly']),
                'weekdays' => array_values($checkIns['weekdays']),
            ],
        ];
    }

    /**
     * @param  array{period: AnalyticsPeriod, locations: list<int>, timezone: string}  $filters
     * @param  array{previousStart: CarbonImmutable, start: CarbonImmutable, end: CarbonImmutable}  $bounds
     * @param  array<string, int>  $buckets
     * @return array{
     *     current: array{total: int, completed: int, turnarounds: list<int>},
     *     previous: array{total: int, completed: int, turnarounds: list<int>},
     *     volume: array<string, int>,
     *     statuses: array<string, int>,
     *     locations: array<int, array{total: int, completed: int, turnarounds: list<int>}>,
     *     hourly: array<int, int>,
     *     weekdays: array<int, int>,
     * }
     */
    private function checkIns(User $user, array $filters, array $bounds, array $buckets): array
    {
        $empty = ['total' => 0, 'completed' => 0, 'turnarounds' => []];
        $stats = [
            'current' => $empty,
            'previous' => $empty,
            'volume' => $buckets,
            'statuses' => [...array_fill_keys(array_column(CheckInStatus::cases(), 'value'), 0), DashboardCheckIns::ARCHIVED => 0],
            'locations' => [],
            'hourly' => array_fill(0, 24, 0),
            'weekdays' => array_fill(0, 7, 0),
        ];

        $rows = $this->inRange(CheckIn::query()->withTrashed()->visibleTo($user), 'created_at', $filters, $bounds)
            ->toBase()
            ->select(['location_id', 'status', 'deleted_at', 'created_at', 'completed_at'])
            ->cursor();

        foreach ($rows as $row) {
            $submitted = $this->utc($row->created_at);
            $isCompleted = $row->status === CheckInStatus::Completed->value;
            $turnaround = $isCompleted && $row->completed_at !== null
                ? max(0, (int) round(($this->utc($row->completed_at)->getTimestamp() - $submitted->getTimestamp()) / 60))
                : null;
            $side = $submitted->lessThan($bounds['start']) ? 'previous' : 'current';

            $stats[$side]['total']++;
            $stats[$side]['completed'] += (int) $isCompleted;
            if ($turnaround !== null) {
                $stats[$side]['turnarounds'][] = $turnaround;
            }

            if ($side === 'previous') {
                continue;
            }

            $local = $submitted->setTimezone($filters['timezone']);
            $stats['volume'][$filters['period']->bucketKey($local)]++;
            $stats['statuses'][$row->deleted_at !== null ? DashboardCheckIns::ARCHIVED : $row->status]++;
            $stats['hourly'][$local->hour]++;
            $stats['weekdays'][$local->dayOfWeekIso - 1]++;

            $location = &$stats['locations'][(int) $row->location_id];
            $location ??= $empty;
            $location['total']++;
            $location['completed'] += (int) $isCompleted;
            if ($turnaround !== null) {
                $location['turnarounds'][] = $turnaround;
            }
            unset($location);
        }

        return $stats;
    }

    /**
     * @param  array{period: AnalyticsPeriod, locations: list<int>, timezone: string}  $filters
     * @param  array{previousStart: CarbonImmutable, start: CarbonImmutable, end: CarbonImmutable}  $bounds
     * @param  array<string, int>  $buckets
     * @return array{
     *     current: array{total: int, noShows: int, due: int},
     *     previous: array{total: int, noShows: int, due: int},
     *     volume: array<string, int>,
     *     statuses: array<string, int>,
     *     locations: array<int, array{total: int, noShows: int, due: int}>,
     * }
     */
    private function appointments(User $user, array $filters, array $bounds, array $buckets): array
    {
        $empty = ['total' => 0, 'noShows' => 0, 'due' => 0];
        $stats = [
            'current' => $empty,
            'previous' => $empty,
            'volume' => $buckets,
            'statuses' => [...array_fill_keys(array_column(AppointmentStatus::cases(), 'value'), 0), DashboardCheckIns::ARCHIVED => 0],
            'locations' => [],
        ];
        $due = [AppointmentStatus::NoShow->value, AppointmentStatus::CheckedIn->value, AppointmentStatus::Completed->value];

        $rows = $this->inRange(Appointment::query()->withTrashed()->visibleTo($user), 'scheduled_for', $filters, $bounds)
            ->toBase()
            ->select(['location_id', 'status', 'deleted_at', 'scheduled_for'])
            ->cursor();

        foreach ($rows as $row) {
            $scheduled = $this->utc($row->scheduled_for);
            $isNoShow = $row->status === AppointmentStatus::NoShow->value;
            $isDue = in_array($row->status, $due, true);
            $side = $scheduled->lessThan($bounds['start']) ? 'previous' : 'current';

            $stats[$side]['total']++;
            $stats[$side]['noShows'] += (int) $isNoShow;
            $stats[$side]['due'] += (int) $isDue;

            if ($side === 'previous') {
                continue;
            }

            $stats['volume'][$filters['period']->bucketKey($scheduled->setTimezone($filters['timezone']))]++;
            $stats['statuses'][$row->deleted_at !== null ? DashboardCheckIns::ARCHIVED : $row->status]++;

            $location = &$stats['locations'][(int) $row->location_id];
            $location ??= $empty;
            $location['total']++;
            $location['noShows'] += (int) $isNoShow;
            $location['due'] += (int) $isDue;
            unset($location);
        }

        return $stats;
    }

    /**
     * One row per location in scope, busiest first: every location the user may see (or
     * the ones filtered to), plus any archived location that still had activity.
     *
     * @param  array{period: AnalyticsPeriod, locations: list<int>, timezone: string}  $filters
     * @param  array<int, array{total: int, completed: int, turnarounds: list<int>}>  $checkIns
     * @param  array<int, array{total: int, noShows: int, due: int}>  $appointments
     * @return list<array{id: int, name: string, abbreviation: string, checkIns: int, completionRate: float|null, medianTurnaround: int|null, appointments: int, noShowRate: float|null}>
     */
    private function ranking(User $user, array $filters, array $checkIns, array $appointments): array
    {
        $visibleIds = $user->dashboardLocationIds();
        $activeIds = array_keys($checkIns + $appointments);

        $rows = Location::query()
            ->withTrashed()
            ->where(fn (Builder $query): Builder => $query->whereKey($activeIds)->orWhereNull('deleted_at'))
            ->when($visibleIds !== null, fn (Builder $query): Builder => $query->whereKey($visibleIds))
            ->when($filters['locations'], fn (Builder $query, array $locations): Builder => $query->whereKey($locations))
            ->get(['id', 'name', 'abbreviation'])
            ->map(function (Location $location) use ($checkIns, $appointments): array {
                $checkInStats = $checkIns[$location->id] ?? ['total' => 0, 'completed' => 0, 'turnarounds' => []];
                $appointmentStats = $appointments[$location->id] ?? ['total' => 0, 'noShows' => 0, 'due' => 0];

                return [
                    'id' => $location->id,
                    'name' => $location->name,
                    'abbreviation' => $location->abbreviation,
                    'checkIns' => $checkInStats['total'],
                    'completionRate' => $this->rate($checkInStats['completed'], $checkInStats['total']),
                    'medianTurnaround' => $this->median($checkInStats['turnarounds']),
                    'appointments' => $appointmentStats['total'],
                    'noShowRate' => $this->rate($appointmentStats['noShows'], $appointmentStats['due']),
                ];
            })
            ->sortBy([['checkIns', 'desc'], ['appointments', 'desc'], ['name', 'asc']]);

        return array_values($rows->all());
    }

    /**
     * Both periods in one query; the caller splits rows at the current period's start.
     *
     * @template TModel of CheckIn|Appointment
     *
     * @param  Builder<TModel>  $query
     * @param  'created_at'|'scheduled_for'  $column
     * @param  array{period: AnalyticsPeriod, locations: list<int>, timezone: string}  $filters
     * @param  array{previousStart: CarbonImmutable, start: CarbonImmutable, end: CarbonImmutable}  $bounds
     * @return Builder<TModel>
     */
    private function inRange(Builder $query, string $column, array $filters, array $bounds): Builder
    {
        return $query
            ->when($filters['locations'], fn (Builder $query, array $locations): Builder => $query->whereIn('location_id', $locations))
            ->where($column, '>=', $bounds['previousStart']->utc())
            ->where($column, '<', $bounds['end']->utc());
    }

    private function utc(string $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value, config('app.timezone'));
    }

    /**
     * A percentage to one decimal, or null when there is nothing to divide.
     */
    private function rate(int $part, int $whole): ?float
    {
        return $whole === 0 ? null : round($part / $whole * 100, 1);
    }

    /**
     * @param  list<int>  $values
     */
    private function median(array $values): ?int
    {
        if ($values === []) {
            return null;
        }

        sort($values);
        $middle = intdiv(count($values), 2);

        return count($values) % 2 === 1
            ? $values[$middle]
            : (int) round(($values[$middle - 1] + $values[$middle]) / 2);
    }
}
