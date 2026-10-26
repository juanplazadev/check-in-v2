<?php

declare(strict_types=1);

namespace App\Schedule;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

/**
 * The eleven US federal holidays, worked out from their fixed rules, for the
 * closures calendar to suggest. Each falls on its actual date, not the
 * weekday it is observed on when it lands on a weekend.
 */
final readonly class UsFederalHolidays
{
    /**
     * Every holiday from one date to another, both included, in date order.
     *
     * @return list<array{date: string, name: string}>
     */
    public function between(CarbonInterface $from, CarbonInterface $to): array
    {
        $holidays = [];

        for ($year = $from->year; $year <= $to->year; $year++) {
            foreach ($this->year($year) as $date => $name) {
                if ($date >= $from->toDateString() && $date <= $to->toDateString()) {
                    $holidays[] = ['date' => $date, 'name' => $name];
                }
            }
        }

        return $holidays;
    }

    /**
     * @return array<string, string> Names keyed by Y-m-d, in date order.
     */
    private function year(int $year): array
    {
        return [
            $this->fixed($year, 1, 1) => "New Year's Day",
            $this->nth($year, 1, 3, CarbonInterface::MONDAY) => 'Martin Luther King Jr. Day',
            $this->nth($year, 2, 3, CarbonInterface::MONDAY) => "Washington's Birthday",
            Date::create($year, 5)->lastOfMonth(CarbonInterface::MONDAY)->toDateString() => 'Memorial Day',
            $this->fixed($year, 6, 19) => 'Juneteenth',
            $this->fixed($year, 7, 4) => 'Independence Day',
            $this->nth($year, 9, 1, CarbonInterface::MONDAY) => 'Labor Day',
            $this->nth($year, 10, 2, CarbonInterface::MONDAY) => 'Columbus Day',
            $this->fixed($year, 11, 11) => 'Veterans Day',
            $this->nth($year, 11, 4, CarbonInterface::THURSDAY) => 'Thanksgiving Day',
            $this->fixed($year, 12, 25) => 'Christmas Day',
        ];
    }

    private function fixed(int $year, int $month, int $day): string
    {
        return Date::create($year, $month, $day)->toDateString();
    }

    /**
     * The nth given weekday of a month, e.g. the fourth Thursday of November.
     */
    private function nth(int $year, int $month, int $nth, int $weekday): string
    {
        return Date::create($year, $month)->nthOfMonth($nth, $weekday)->toDateString();
    }
}
