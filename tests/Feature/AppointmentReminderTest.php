<?php

declare(strict_types=1);

use App\Enums\NotificationChannel;
use App\Enums\RecordHistoryEvent;
use App\Models\Appointment;
use App\Models\Location;
use App\Models\User;
use App\Notifications\AppointmentReminder;
use App\Notifications\Channels\SmsChannel;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

/**
 * Phoenix is UTC-7 year round, so these instants stay readable and nothing here
 * is accidentally a daylight-saving test.
 *
 * The slot is Thu 06:00 Phoenix (13:00 UTC); its reminder moment is Wed 17:00
 * Phoenix, which is Thu 00:00 UTC.
 */
const SLOT_LOCAL = '2026-08-13 06:00';

const BEFORE_REMINDER = '2026-08-12 19:00:00';   // UTC, booking time

const AFTER_REMINDER = '2026-08-13 01:00:00';    // UTC, an hour past the moment

/**
 * User has no HasFactory trait, so the suite builds drivers by hand throughout.
 *
 * @param  array<string, mixed>  $attributes
 */
function reminderDriver(array $attributes = []): User
{
    return User::query()->create([
        'name' => 'John Driver',
        'email' => 'reminder-driver@example.com',
        'password' => 'secret-password',
        'email_verified_at' => now(),
        ...$attributes,
    ]);
}

/**
 * Books a Phoenix slot at a moment that is safely before its own reminder.
 *
 * @param  array<string, mixed>  $attributes
 */
function reminderBooking(?User $user, array $attributes = [], string $localSlot = SLOT_LOCAL): Appointment
{
    Date::setTestNow(Date::parse(BEFORE_REMINDER, 'UTC'));

    $location = Location::factory()->appointmentsOnly()->create(['timezone' => 'America/Phoenix']);

    $factory = Appointment::factory()->atLocation($location);

    if ($user instanceof User) {
        $factory = $factory->forUser($user);
    }

    return $factory->create([
        'scheduled_for' => Date::parse($localSlot, 'America/Phoenix')->utc(),
        'created_at' => Date::now(),
        ...$attributes,
    ]);
}

/** Move the clock past the reminder moment and run the sweep. */
function runReminderSweep(): void
{
    Date::setTestNow(Date::parse(AFTER_REMINDER, 'UTC'));

    test()->artisan('appointments:send-reminders')->assertSuccessful();
}

/*
|--------------------------------------------------------------------------
| The happy path
|--------------------------------------------------------------------------
*/

it('reminds a driver the day before and stamps the booking', function (): void {
    Notification::fake();

    $driver = reminderDriver();
    $appointment = reminderBooking($driver);

    runReminderSweep();

    Notification::assertSentTo($driver, AppointmentReminder::class);

    expect($appointment->refresh()->reminder_sent_at)->not->toBeNull();
});

/*
 * The guarantee the reminder_sent_at column exists for. The command runs every
 * hour; without this it would remind the same driver on every pass until the
 * slot arrived.
 */
it('never reminds the same booking twice', function (): void {
    Notification::fake();

    $driver = reminderDriver();
    reminderBooking($driver);

    runReminderSweep();
    Notification::assertSentTimes(AppointmentReminder::class, 1);

    // A second sweep an hour later, exactly as the scheduler would run it.
    Date::setTestNow(Date::parse(AFTER_REMINDER, 'UTC')->addHour());
    $this->artisan('appointments:send-reminders')->assertSuccessful();

    Notification::assertSentTimes(AppointmentReminder::class, 1);
});

it('honours the driver channel preference', function (): void {
    Notification::fake();

    $driver = reminderDriver([
        'cellphone' => '+12015550123',
        'notification_channel' => NotificationChannel::Sms,
    ]);

    reminderBooking($driver);

    runReminderSweep();

    Notification::assertSentTo(
        $driver,
        AppointmentReminder::class,
        fn (AppointmentReminder $notification, array $channels): bool => $channels === [SmsChannel::class],
    );
});

it('joins the booking trail as a sent notification', function (): void {
    // Deliberately not faking notifications: the trail row is written by
    // RecordNotificationHistory off the NotificationSent event, so faking would
    // assert nothing. This driver has no cellphone, so the reminder goes out on
    // mail alone, which the array mailer handles inline on the sync queue.
    $appointment = reminderBooking(reminderDriver());

    runReminderSweep();

    $row = $appointment->history()
        ->where('event', RecordHistoryEvent::NotificationSent)
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->subject)->toBe('appointment-reminder');
});

/*
|--------------------------------------------------------------------------
| Who is left alone
|--------------------------------------------------------------------------
*/

it('leaves guest bookings alone', function (): void {
    Notification::fake();

    $appointment = reminderBooking(null);

    runReminderSweep();

    Notification::assertNothingSent();

    expect($appointment->refresh()->reminder_sent_at)->toBeNull();
});

it('leaves a driver who switched reminders off alone', function (): void {
    Notification::fake();

    $driver = reminderDriver(['notify_appointment_reminder' => false]);
    reminderBooking($driver);

    runReminderSweep();

    Notification::assertNothingSent();
});

it('leaves a cancelled booking alone', function (): void {
    Notification::fake();

    $driver = reminderDriver();
    reminderBooking($driver, ['status' => App\Enums\AppointmentStatus::Cancelled]);

    runReminderSweep();

    Notification::assertNothingSent();
});

it('leaves an already-reminded booking alone', function (): void {
    Notification::fake();

    $driver = reminderDriver();
    reminderBooking($driver, ['reminder_sent_at' => Date::parse(BEFORE_REMINDER, 'UTC')]);

    runReminderSweep();

    Notification::assertNothingSent();
});

it('leaves a booking that is still days out alone', function (): void {
    Notification::fake();

    $driver = reminderDriver();
    reminderBooking($driver, localSlot: '2026-08-20 06:00');

    runReminderSweep();

    Notification::assertNothingSent();
});

it('leaves a booking whose slot has already passed alone', function (): void {
    Notification::fake();

    $driver = reminderDriver();
    reminderBooking($driver);

    // The scheduler was down and only comes back after the appointment.
    Date::setTestNow(Date::parse('2026-08-13 20:00:00', 'UTC'));
    $this->artisan('appointments:send-reminders')->assertSuccessful();

    Notification::assertNothingSent();
});

it('leaves a booking made after its own reminder moment alone', function (): void {
    Notification::fake();

    $driver = reminderDriver();
    $appointment = reminderBooking($driver);

    // Re-stamp as booked at 8pm Phoenix, past the 5pm cutoff.
    $appointment->forceFill([
        'created_at' => Date::parse('2026-08-12 20:00', 'America/Phoenix')->utc(),
    ])->save();

    runReminderSweep();

    Notification::assertNothingSent();
});
