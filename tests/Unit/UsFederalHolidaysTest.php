<?php

declare(strict_types=1);

use App\Schedule\UsFederalHolidays;
use Illuminate\Support\Facades\Date;

it('works out every federal holiday of a year from its rule', function (): void {
    $holidays = new UsFederalHolidays()->between(Date::parse('2026-01-01'), Date::parse('2026-12-31'));

    expect(array_column($holidays, 'name', 'date'))->toBe([
        '2026-01-01' => "New Year's Day",
        '2026-01-19' => 'Martin Luther King Jr. Day',
        '2026-02-16' => "Washington's Birthday",
        '2026-05-25' => 'Memorial Day',
        '2026-06-19' => 'Juneteenth',
        '2026-07-04' => 'Independence Day',
        '2026-09-07' => 'Labor Day',
        '2026-10-12' => 'Columbus Day',
        '2026-11-11' => 'Veterans Day',
        '2026-11-26' => 'Thanksgiving Day',
        '2026-12-25' => 'Christmas Day',
    ]);
});

it('keeps only the holidays in range, across years', function (): void {
    $holidays = new UsFederalHolidays()->between(Date::parse('2026-12-01'), Date::parse('2027-01-31'));

    expect(array_column($holidays, 'date'))->toBe(['2026-12-25', '2027-01-01', '2027-01-18']);
});
