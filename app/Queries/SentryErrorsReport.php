<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\ErrorPeriod;
use App\Services\SentryService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;

/**
 * The errors page's headline figures, read from Sentry and cached for five
 * minutes:
 * - errors and dropped events come from outcomes, which Sentry keeps per
 *   project but not per environment, so the environment filter skips them;
 * - users affected is distinct users across error events, or, when the
 *   plan's events API refuses, the most users any one unresolved issue hit;
 * - quota is accepted errors since the billing day against the configured
 *   monthly quota, since the API does not expose the plan's.
 */
final readonly class SentryErrorsReport
{
    /** Outcomes that mean Sentry received an error but did not keep it. */
    private const array DROPPED_OUTCOMES = ['filtered', 'rate_limited', 'invalid', 'abuse', 'client_discard', 'cardinality_limited'];

    public function __construct(private SentryService $sentry) {}

    /**
     * @param  array{period: ErrorPeriod, environment: ?string}  $filters
     * @return array{
     *     summary: array{
     *         errors: array{value: int, previous: int},
     *         dropped: array{value: int, previous: int, breakdown: array<string, int>},
     *         usersAffected: array{value: int, exact: bool},
     *         unresolved: int,
     *         newIssues: int,
     *     },
     *     volume: array{interval: string, labels: list<string>, accepted: list<int>, dropped: list<int>},
     *     quota: array{used: int, limit: int, percent: float, periodStart: string, resetsAt: string, daysUntilReset: int},
     *     environments: list<string>,
     * }
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function execute(array $filters): array
    {
        $period = $filters['period'];
        $environment = $filters['environment'];

        return Cache::remember(
            'sentry:errors-report:'.$period->value.':'.($environment ?? '*'),
            now()->addMinutes(5),
            function () use ($period, $environment): array {
                $outcomes = $this->outcomes($period);

                return [
                    'summary' => [
                        ...$outcomes['summary'],
                        'usersAffected' => $this->usersAffected($period, $environment),
                        'unresolved' => $this->sentry->issueCount('is:unresolved', $period->value, $environment),
                        'newIssues' => $this->sentry->issueCount('is:unresolved firstSeen:-'.$period->value, $period->value, $environment),
                    ],
                    'volume' => $outcomes['volume'],
                    'quota' => $this->quota(),
                    'environments' => $this->sentry->environments(),
                ];
            },
        );
    }

    /**
     * Accepted and dropped errors for the period and the one before it, and
     * the period's buckets for the chart, from one outcomes request that
     * spans both.
     *
     * @return array{
     *     summary: array{errors: array{value: int, previous: int}, dropped: array{value: int, previous: int, breakdown: array<string, int>}},
     *     volume: array{interval: string, labels: list<string>, accepted: list<int>, dropped: list<int>},
     * }
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    private function outcomes(ErrorPeriod $period): array
    {
        $bounds = $period->bounds();
        $stats = $this->sentry->outcomes($bounds['previousStart'], $bounds['end'], $period->interval());

        $current = array_keys(array_filter(
            $stats['intervals'],
            fn (string $interval): bool => CarbonImmutable::parse($interval)->greaterThanOrEqualTo($bounds['start'])
                && CarbonImmutable::parse($interval)->lessThan($bounds['end']),
        ));

        $sum = fn (array $series, bool $inCurrent): int => array_sum(array_filter(
            $series,
            fn (int $index): bool => in_array($index, $current, true) === $inCurrent,
            ARRAY_FILTER_USE_KEY,
        ));

        $accepted = $stats['outcomes']['accepted']['series'] ?? [];
        $droppedSeries = array_fill(0, count($stats['intervals']), 0);
        $breakdown = [];

        foreach (self::DROPPED_OUTCOMES as $outcome) {
            $series = $stats['outcomes'][$outcome]['series'] ?? [];
            $breakdown[$outcome] = $sum($series, true);

            foreach ($series as $index => $count) {
                $droppedSeries[$index] = ($droppedSeries[$index] ?? 0) + $count;
            }
        }

        return [
            'summary' => [
                'errors' => ['value' => $sum($accepted, true), 'previous' => $sum($accepted, false)],
                'dropped' => [
                    'value' => $sum($droppedSeries, true),
                    'previous' => $sum($droppedSeries, false),
                    'breakdown' => array_filter($breakdown),
                ],
            ],
            'volume' => [
                'interval' => $period->interval(),
                'labels' => array_map(fn (int $index): string => $stats['intervals'][$index], $current),
                'accepted' => array_map(fn (int $index): int => $accepted[$index] ?? 0, $current),
                'dropped' => array_map(fn (int $index): int => $droppedSeries[$index] ?? 0, $current),
            ],
        ];
    }

    /**
     * @return array{value: int, exact: bool}
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    private function usersAffected(ErrorPeriod $period, ?string $environment): array
    {
        $distinct = $this->sentry->usersAffected($period->value, $environment);

        if ($distinct !== null) {
            return ['value' => $distinct, 'exact' => true];
        }

        $top = $this->sentry->issues([
            'query' => 'is:unresolved',
            'sort' => 'user',
            'statsPeriod' => $period->value,
            'environment' => $environment,
            'cursor' => null,
            'limit' => 1,
        ])['issues'][0] ?? null;

        return ['value' => (int) ($top['userCount'] ?? 0), 'exact' => false];
    }

    /**
     * @return array{used: int, limit: int, percent: float, periodStart: string, resetsAt: string, daysUntilReset: int}
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    private function quota(): array
    {
        $now = Date::now()->toImmutable()->utc();
        $billingDay = max(1, min(28, (int) config('services.sentry_api.billing_day')));
        $start = $now->setDay($billingDay)->startOfDay();

        if ($start->greaterThan($now)) {
            $start = $start->subMonthNoOverflow();
        }

        $resetsAt = $start->addMonthNoOverflow();
        $limit = max(1, (int) config('services.sentry_api.error_quota'));
        $used = $this->sentry->outcomes($start, $now->startOfHour()->addHour(), '1d')['outcomes']['accepted']['total'] ?? 0;

        return [
            'used' => $used,
            'limit' => $limit,
            'percent' => round($used / $limit * 100, 1),
            'periodStart' => $start->toIso8601String(),
            'resetsAt' => $resetsAt->toIso8601String(),
            'daysUntilReset' => (int) ceil($now->diffInDays($resetsAt)),
        ];
    }
}
