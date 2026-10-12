<?php

declare(strict_types=1);

use App\Actions\SendAppointmentCopyAction;
use App\Enums\NotificationChannel;
use App\Models\Appointment;
use App\Models\Location;
use App\Models\User;
use App\Notifications\AppointmentCopy;
use App\Notifications\Channels\SmsChannel;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\post;

/**
 * @param  array<string, mixed>  $attributes
 */
function bookingDriver(array $attributes = []): User
{
    return User::query()->create([
        'name' => 'John Driver',
        'email' => 'booking-driver@example.com',
        'password' => 'secret-password',
        ...$attributes,
    ]);
}

/**
 * Books through the real endpoint, the way AppointmentBookedEmailTest does, so
 * the whole controller-to-action path is exercised.
 */
function bookAs(?User $user): void
{
    if ($user instanceof User) {
        test()->actingAs($user);
    }

    $location = Location::factory()->appointmentsOnly()->create();

    post(route('appointment.gate', $location->uuid), ['uuid' => $location->uuid]);

    post(route('appointment.store', $location->uuid), [
        'datetime' => now()->addHour()->startOfHour()->format('Y-m-d H:i:s'),
        'po_numbers' => ['SO-00001'],
        'drivers_name' => 'John Doe',
        'drivers_cellphone' => '(201) 555-0123',
    ])->assertSessionHasNoErrors();
}

beforeEach(function (): void {
    Date::setTestNow(Date::today('UTC')->setTime(8, 0));
    Notification::fake();
});

it('sends the driver their copy by default', function (): void {
    $driver = bookingDriver();

    bookAs($driver);

    Notification::assertSentTo($driver, AppointmentCopy::class);
});

it('texts a guest their booking confirmation', function (): void {
    // See CheckInCopyNotificationTest: the form requires a cellphone and
    // collects no email, so a guest is reachable by text alone.
    bookAs(null);

    Notification::assertSentOnDemand(
        AppointmentCopy::class,
        fn (AppointmentCopy $notification, array $channels, object $notifiable): bool => $channels === [SmsChannel::class]
            && $notifiable->routes['sms'] === '+12015550123',
    );
});

it('sends a guest a text with the reference and no link', function (): void {
    $appointment = Appointment::factory()->create(['drivers_cellphone' => '+12015550123']);

    $body = new AppointmentCopy($appointment)
        ->toSms(Notification::route('sms', '+12015550123'))
        ->body;

    expect($body)->toContain($appointment->reference_number)
        ->and($body)->not->toContain('http');
});

it('sends nothing when the driver has turned the copy off', function (): void {
    $driver = bookingDriver(['notify_appointment_copy' => false]);

    bookAs($driver);

    Notification::assertNothingSent();
});

/*
 * The check-in toggle is a separate column, so turning one off must not silence
 * the other.
 */
it('still sends when only the check-in copy is turned off', function (): void {
    $driver = bookingDriver(['notify_check_in_copy' => false]);

    bookAs($driver);

    Notification::assertSentTo($driver, AppointmentCopy::class);
});

it('sends on the channels the driver chose', function (NotificationChannel $channel, array $expected): void {
    $driver = bookingDriver([
        'cellphone' => '+12015550123',
        'notification_channel' => $channel,
    ]);

    bookAs($driver);

    Notification::assertSentTo(
        $driver,
        AppointmentCopy::class,
        fn (AppointmentCopy $notification, array $channels): bool => $channels === $expected,
    );
})->with([
    'email' => [NotificationChannel::Email, ['mail']],
    'text' => [NotificationChannel::Sms, [SmsChannel::class]],
    'both' => [NotificationChannel::Both, ['mail', SmsChannel::class]],
]);

it('carries the locale stamped on the booking', function (): void {
    $driver = bookingDriver();
    $appointment = Appointment::factory()->forUser($driver)->create(['locale' => 'fr']);

    resolve(SendAppointmentCopyAction::class)->handle($appointment);

    Notification::assertSentTo(
        $driver,
        AppointmentCopy::class,
        fn (AppointmentCopy $notification): bool => $notification->locale === 'fr',
    );
});

it('renders the text message with the reference and a short link to the record', function (): void {
    $driver = bookingDriver(['cellphone' => '+12015550123']);
    $appointment = Appointment::factory()->forUser($driver)->create();

    $message = new AppointmentCopy($appointment)->toSms($driver);

    expect($message->to)->toBe('+12015550123')
        ->and($message->body)->toContain($appointment->reference_number)
        ->and($message->body)->toContain($appointment->shortUrl())
        // The old link carried the 36-character uuid inline.
        ->and($message->body)->not->toContain($appointment->uuid);
});

it('keeps the text message inside one segment in every locale', function (string $locale): void {
    App::setLocale($locale);

    $driver = bookingDriver(['cellphone' => '+12015550123']);
    $appointment = Appointment::factory()->forUser($driver)->create();

    expect(mb_strlen(new AppointmentCopy($appointment)->toSms($driver)->body))
        ->toBeLessThanOrEqual(160);
})->with(['en', 'es', 'fr']);
