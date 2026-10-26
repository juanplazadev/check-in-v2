<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

/**
 * The analytics page's preset periods. Each is a run of whole calendar days
 * (or months) in the viewer's timezone that ends with the current one, so
 * "last 7 days" is today and the six days before it.
 */
enum AnalyticsPeriod: string
{
    case Today = 'today';
    case Last7Days = '7d';
    case Last30Days = '30d';
    case Last90Days = '90d';
    case Last12Months = '12m';

    public const self DEFAULT = self::Last30Days;

    public function label(): string
    {
        return match ($this) {
            self::Today => 'Today',
            self::Last7Days => 'Last 7 days',
            self::Last30Days => 'Last 30 days',
            self::Last90Days => 'Last 90 days',
            self::Last12Months => 'Last 12 months',
        };
    }

    /**
     * How the previous period is named beside a change, as in "vs yesterday".
     */
    public function previousLabel(): string
    {
        return match ($this) {
            self::Today => 'yesterday',
            self::Last12Months => 'prior 12 months',
            default => 'prior '.$this->bucketCount().' days',
        };
    }

    /**
     * The width of one chart bucket: hours for today, months for a year, days otherwise.
     *
     * @return 'hour'|'day'|'month'
     */
    public function bucket(): string
    {
        return match ($this) {
            self::Today => 'hour',
            self::Last12Months => 'month',
            default => 'day',
        };
    }

    /**
     * The first bucket's start, in the viewer's timezone.
     */
    public function start(string $timezone): CarbonImmutable
    {
        $now = Date::now()->toImmutable()->setTimezone($timezone);

        return match ($this) {
            self::Today => $now->startOfDay(),
            self::Last12Months => $now->startOfMonth()->subMonths($this->bucketCount() - 1),
            default => $now->startOfDay()->subDays($this->bucketCount() - 1),
        };
    }

    /**
     * The half-open bounds of this period and the one just before it, in the viewer's
     * timezone. Compare them against UTC columns with ->utc().
     *
     * @return array{previousStart: CarbonImmutable, start: CarbonImmutable, end: CarbonImmutable}
     */
    public function bounds(string $timezone): array
    {
        $start = $this->start($timezone);

        return [
            'previousStart' => $this->step($start, -$this->bucketCount()),
            'start' => $start,
            'end' => $this->step($start, $this->bucketCount()),
        ];
    }

    /**
     * Each bucket's start in the viewer's timezone, oldest first.
     *
     * @return list<CarbonImmutable>
     */
    public function buckets(string $timezone): array
    {
        $start = $this->start($timezone);

        return array_map(fn (int $offset): CarbonImmutable => $this->step($start, $offset), range(0, $this->bucketCount() - 1));
    }

    /**
     * The key of the bucket a local time falls in, matching the keys of buckets().
     */
    public function bucketKey(CarbonImmutable $local): string
    {
        return match ($this->bucket()) {
            'hour' => $local->format('Y-m-d\TH:00:00'),
            'month' => $local->format('Y-m-01\T00:00:00'),
            default => $local->format('Y-m-d\T00:00:00'),
        };
    }

    private function bucketCount(): int
    {
        return match ($this) {
            self::Today => 24,
            self::Last7Days => 7,
            self::Last30Days => 30,
            self::Last90Days => 90,
            self::Last12Months => 12,
        };
    }

    /**
     * Moves by whole buckets from a bucket start. Hours are set on the wall clock
     * rather than added, so a DST day still yields one bucket per hour label.
     */
    private function step(CarbonImmutable $from, int $buckets): CarbonImmutable
    {
        return match ($this->bucket()) {
            'hour' => $from->addDays(intdiv($buckets, 24))->setTime($buckets % 24, 0),
            'month' => $from->addMonthsNoOverflow($buckets),
            default => $from->addDays($buckets),
        };
    }
}
