<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

/**
 * The errors page's preset periods, as Sentry statsPeriod values. Capped at
 * 30 days, the event retention of Sentry's free plan.
 */
enum ErrorPeriod: string
{
    case Last24Hours = '24h';
    case Last7Days = '7d';
    case Last14Days = '14d';
    case Last30Days = '30d';

    public const self DEFAULT = self::Last14Days;

    public function label(): string
    {
        return match ($this) {
            self::Last24Hours => 'Last 24 hours',
            self::Last7Days => 'Last 7 days',
            self::Last14Days => 'Last 14 days',
            self::Last30Days => 'Last 30 days',
        };
    }

    /**
     * How the previous period is named beside a change, as in "vs prior 24 hours".
     */
    public function previousLabel(): string
    {
        return 'prior '.mb_strtolower(mb_substr($this->label(), 5));
    }

    /**
     * The width of one chart bucket, as a Sentry stats interval.
     *
     * @return '1h'|'1d'
     */
    public function interval(): string
    {
        return $this === self::Last24Hours ? '1h' : '1d';
    }

    /**
     * The half-open bounds of this period and the one just before it, aligned
     * to whole buckets so both halves of the chart line up.
     *
     * @return array{previousStart: CarbonImmutable, start: CarbonImmutable, end: CarbonImmutable}
     */
    public function bounds(): array
    {
        $now = Date::now()->toImmutable()->utc();

        if ($this === self::Last24Hours) {
            $end = $now->startOfHour()->addHour();

            return ['previousStart' => $end->subHours(48), 'start' => $end->subHours(24), 'end' => $end];
        }

        $days = (int) $this->value;
        $end = $now->startOfDay()->addDay();

        return ['previousStart' => $end->subDays($days * 2), 'start' => $end->subDays($days), 'end' => $end];
    }
}
