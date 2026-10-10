<?php

declare(strict_types=1);

use App\Appointment\ReminderSchedule;
use App\Models\Appointment;
use App\Models\Location;
use Illuminate\Support\Facades\Date;

/**
 * Phoenix on purpose: UTC-7 all year, so the expected instants stay readable
 * and no assertion here is quietly a daylight-saving test.
 */
const PHOENIX = 'America/Phoenix';

function phoenixLocationForReminders(): Location
{
    return Location::factory()->appointmentsOnly()->create(['timezone' => PHOENIX]);
}

/**
 * Books a slot at a Phoenix-local time, with the booking itself made well
 * before its own reminder moment unless the caller says otherwise.
 */
function bookingAt(string $localDateTime, ?string $createdAt = null): Appointment
{
    $scheduledFor = Date::parse($localDateTime, PHOENIX)->utc();

    return Appointment::factory()
        ->atLocation(phoenixLocationForReminders())
        ->create([
            'scheduled_for' => $scheduledFor,
            'created_at' => $createdAt === null
                ? $scheduledFor->copy()->subWeek()
                : Date::parse($createdAt, PHOENIX)->utc(),
        ]);
}

function schedule(): ReminderSchedule
{
    return resolve(ReminderSchedule::class);
}

/*
|--------------------------------------------------------------------------
| The reminder moment
|--------------------------------------------------------------------------
*/

it('puts the reminder at the configured hour the day before, in the location timezone', function (): void {
    // Thu 6am Phoenix. The day before at 5pm Phoenix is Thu 00:00 UTC.
    $appointment = bookingAt('2026-08-13 06:00');

    expect(schedule()->reminderMomentFor($appointment)->toDateTimeString())
        ->toBe('2026-08-13 00:00:00');
});

it('anchors on the local day, so a late slot reminds at the same hour as an early one', function (): void {
    $early = bookingAt('2026-08-13 06:00');
    $late = bookingAt('2026-08-13 22:00');

    expect(schedule()->reminderMomentFor($late)->toDateTimeString())
        ->toBe(schedule()->reminderMomentFor($early)->toDateTimeString());
});

it('follows the configured hour', function (): void {
    config(['app.appointment_reminder_hour' => 8]);

    // 8am Phoenix the day before is 15:00 UTC.
    expect(schedule()->reminderMomentFor(bookingAt('2026-08-13 06:00'))->toDateTimeString())
        ->toBe('2026-08-12 15:00:00');
});

/*
|--------------------------------------------------------------------------
| Whether it is due
|--------------------------------------------------------------------------
*/

it('is not due before the reminder moment', function (): void {
    $appointment = bookingAt('2026-08-13 06:00');

    $oneMinuteEarly = Date::parse('2026-08-12 23:59:00', 'UTC')->toImmutable();

    expect(schedule()->isDue($appointment, $oneMinuteEarly))->toBeFalse();
});

it('is due at the reminder moment and after it', function (): void {
    $appointment = bookingAt('2026-08-13 06:00');

    $atTheMoment = Date::parse('2026-08-13 00:00:00', 'UTC')->toImmutable();
    $anHourLater = Date::parse('2026-08-13 01:00:00', 'UTC')->toImmutable();

    expect(schedule()->isDue($appointment, $atTheMoment))->toBeTrue()
        ->and(schedule()->isDue($appointment, $anHourLater))->toBeTrue();
});

it('is not due once the appointment has already happened', function (): void {
    // Guards the scheduler-outage case: back up after a day down, we must not
    // mail reminders for slots that are already in the past.
    $appointment = bookingAt('2026-08-13 06:00');

    $afterTheSlot = Date::parse('2026-08-13 14:00:00', 'UTC')->toImmutable();

    expect(schedule()->isDue($appointment, $afterTheSlot))->toBeFalse();
});

it('is not due when the booking was made after its own reminder moment', function (): void {
    // Booked 8pm Phoenix for 6am the next morning - already past the 5pm
    // cutoff. Reminding would fire minutes after the booking confirmation.
    $appointment = bookingAt('2026-08-13 06:00', createdAt: '2026-08-12 20:00');

    $justAfterBooking = Date::parse('2026-08-13 04:00:00', 'UTC')->toImmutable();

    expect(schedule()->isDue($appointment, $justAfterBooking))->toBeFalse();
});

it('is still due for a booking made exactly at its reminder moment', function (): void {
    $appointment = bookingAt('2026-08-13 06:00', createdAt: '2026-08-12 17:00');

    $atTheMoment = Date::parse('2026-08-13 00:00:00', 'UTC')->toImmutable();

    expect(schedule()->isDue($appointment, $atTheMoment))->toBeTrue();
});
