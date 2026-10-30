<?php

declare(strict_types=1);

use App\Appointment\AppointmentScheduleResolver;
use App\Models\AppointmentScheduleOverride;
use App\Models\Location;
use Illuminate\Support\Facades\Date;

/**
 * @param  array<string, int>  $appointmentConfig
 */
function makeAppointmentLocation(array $appointmentConfig = [], string $open = '09:00:00', string $close = '17:00:00'): Location
{
    $location = Location::factory()->appointmentsOnly()->create([
        'timezone' => 'UTC',
        'config' => ['appointment' => $appointmentConfig],
    ]);

    $location->appointmentSchedule()->delete();

    foreach (range(0, 6) as $dayOfWeek) {
        $location->appointmentSchedule()->create([
            'day_of_week' => $dayOfWeek,
            'open_time' => $open,
            'close_time' => $close,
        ]);
    }

    return $location->fresh();
}

function resolveWindows(Location $location): array
{
    return resolve(AppointmentScheduleResolver::class)->buildDTO($location, now())->availability;
}

beforeEach(function (): void {
    // Wednesday, 8:00 AM UTC (before a 09:00-17:00 window).
    $this->travelTo(Date::parse('2026-06-17 08:00:00', 'UTC'));
});

it('lists a window per open day within the horizon with interval and buffer applied', function (): void {
    $location = makeAppointmentLocation([
        'max_booking_days_ahead' => 2,
        'slot_interval_minutes' => 15,
        'min_lead_time_minutes' => 60,
        'buffer_before_close_minutes' => 30,
    ]);

    $windows = resolveWindows($location);

    expect($windows)->toHaveCount(3)
        ->and($windows[0]->date)->toBe('2026-06-17')
        ->and($windows[0]->firstSlot)->toBe('09:00:00')
        ->and($windows[0]->lastSlot)->toBe('16:30:00')
        ->and($windows[2]->date)->toBe('2026-06-19');
});

it('pushes today first slot forward by the lead time, rounded up to the interval', function (): void {
    $this->travelTo(Date::parse('2026-06-17 09:40:00', 'UTC'));

    $location = makeAppointmentLocation([
        'max_booking_days_ahead' => 1,
        'slot_interval_minutes' => 15,
        'min_lead_time_minutes' => 60,
        'buffer_before_close_minutes' => 30,
    ]);

    $windows = resolveWindows($location);

    // 09:40 + 60m = 10:40, rounded up to next 15m boundary = 10:45.
    expect($windows[0]->date)->toBe('2026-06-17')
        ->and($windows[0]->firstSlot)->toBe('10:45:00');
});

it('omits today once the lead time pushes past the last bookable slot', function (): void {
    $this->travelTo(Date::parse('2026-06-17 16:45:00', 'UTC'));

    $location = makeAppointmentLocation([
        'max_booking_days_ahead' => 2,
        'slot_interval_minutes' => 15,
        'min_lead_time_minutes' => 60,
        'buffer_before_close_minutes' => 30,
    ]);

    $windows = resolveWindows($location);

    expect($windows)->toHaveCount(2)
        ->and($windows[0]->date)->toBe('2026-06-18')
        ->and($windows[0]->firstSlot)->toBe('09:00:00');
});

it('omits days closed by an override', function (): void {
    $location = makeAppointmentLocation([
        'max_booking_days_ahead' => 2,
        'slot_interval_minutes' => 15,
        'min_lead_time_minutes' => 60,
        'buffer_before_close_minutes' => 30,
    ]);

    AppointmentScheduleOverride::factory()
        ->for($location)
        ->closure()
        ->onDate('2026-06-17')
        ->create();

    $windows = resolveWindows($location->fresh());

    expect($windows)->toHaveCount(2)
        ->and($windows[0]->date)->toBe('2026-06-18');
});

it('shifts the last slot by the buffer before close', function (): void {
    $location = makeAppointmentLocation([
        'max_booking_days_ahead' => 0,
        'slot_interval_minutes' => 30,
        'min_lead_time_minutes' => 0,
        'buffer_before_close_minutes' => 60,
    ]);

    $windows = resolveWindows($location);

    expect($windows[0]->lastSlot)->toBe('16:00:00');
});

it('clamps the horizon to the configured cap', function (): void {
    $location = makeAppointmentLocation([
        'max_booking_days_ahead' => 999,
    ]);

    // Offsets 0..7 inclusive == 8 open days (clamped to the 7-day cap).
    expect(resolveWindows($location))->toHaveCount(8);
});

it('accepts a valid on-grid in-window slot and rejects invalid ones', function (): void {
    $location = makeAppointmentLocation([
        'max_booking_days_ahead' => 2,
        'slot_interval_minutes' => 15,
        'min_lead_time_minutes' => 60,
        'buffer_before_close_minutes' => 30,
    ]);

    $resolver = resolve(AppointmentScheduleResolver::class);

    $valid = Date::parse('2026-06-17 09:00:00', 'UTC');
    $offGrid = Date::parse('2026-06-17 09:07:00', 'UTC');
    $afterLast = Date::parse('2026-06-17 16:45:00', 'UTC');
    $beforeFirst = Date::parse('2026-06-17 08:30:00', 'UTC');
    $beyondHorizon = Date::parse('2026-06-22 09:00:00', 'UTC');

    expect($resolver->isBookableSlot($location, $valid))->toBeTrue()
        ->and($resolver->isBookableSlot($location, $offGrid))->toBeFalse()
        ->and($resolver->isBookableSlot($location, $afterLast))->toBeFalse()
        ->and($resolver->isBookableSlot($location, $beforeFirst))->toBeFalse()
        ->and($resolver->isBookableSlot($location, $beyondHorizon))->toBeFalse();
});

it('accepts a load that finishes by closing time and rejects one that runs past it', function (): void {
    $location = makeAppointmentLocation([
        'max_booking_days_ahead' => 2,
        'slot_interval_minutes' => 15,
        'min_lead_time_minutes' => 60,
        'buffer_before_close_minutes' => 30,
    ]);

    $resolver = resolve(AppointmentScheduleResolver::class);

    $start = Date::parse('2026-06-17 16:00:00', 'UTC');

    expect($resolver->isBookableSlot($location, $start, $start->copy()->addMinutes(60)))->toBeTrue()
        ->and($resolver->isBookableSlot($location, $start, $start->copy()->addMinutes(75)))->toBeFalse();
});
