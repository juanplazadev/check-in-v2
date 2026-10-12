<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Enums\RecordHistoryEvent;
use App\Enums\Role;
use App\Mail\AppointmentCancelled;
use App\Models\Appointment;
use App\Models\Location;
use App\Models\User;
use App\Notifications\AppointmentCancellation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/**
 * User has no HasFactory trait, so the suite builds drivers by hand throughout.
 *
 * @param  array<string, mixed>  $attributes
 */
function cancellingDriver(array $attributes = []): User
{
    return User::query()->create([
        'name' => 'John Driver',
        'email' => 'canceller@example.com',
        'password' => 'secret-password',
        'email_verified_at' => now(),
        ...$attributes,
    ])->assignRole(Role::Driver);
}

/**
 * @param  array<string, mixed>  $config
 */
function cancellableAppointment(User $user, array $config = []): Appointment
{
    $location = Location::factory()->appointmentsOnly()->create(['config' => $config]);

    return Appointment::factory()
        ->forUser($user)
        ->atLocation($location)
        ->scheduledFor(Date::now()->addDays(2))
        ->create();
}

beforeEach(function (): void {
    Date::setTestNow(Date::today('UTC')->setTime(8, 0));
});

/*
|--------------------------------------------------------------------------
| The happy path
|--------------------------------------------------------------------------
*/

it('lets the owner cancel an upcoming booking', function (): void {
    $driver = cancellingDriver();
    $appointment = cancellableAppointment($driver);

    $this->actingAs($driver)
        ->from(route('account.history.appointment', $appointment->uuid))
        ->post(route('account.history.appointment.cancel', $appointment->uuid), [
            'reason' => 'Load cancelled by the shipper',
        ])
        ->assertSessionHasNoErrors();

    $appointment->refresh();

    expect($appointment->status)->toBe(AppointmentStatus::Cancelled)
        ->and($appointment->cancelled_at)->not->toBeNull()
        ->and($appointment->cancelled_reason)->toBe('Load cancelled by the shipper');
});

it('writes a status-changed row to the history trail', function (): void {
    $driver = cancellingDriver();
    $appointment = cancellableAppointment($driver);

    $this->actingAs($driver)
        ->post(route('account.history.appointment.cancel', $appointment->uuid), [
            'reason' => 'Truck in the shop',
        ])
        ->assertSessionHasNoErrors();

    // Through the relation rather than a recordable_type query: a morph map is
    // enforced, so the column holds the alias and not the class name.
    $row = $appointment->history()
        ->where('event', RecordHistoryEvent::StatusChanged)
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->context['from'])->toBe('scheduled')
        ->and($row->context['to'])->toBe('cancelled');
});

it('tells the facility the slot is free', function (): void {
    Mail::fake();

    $driver = cancellingDriver();
    $appointment = cancellableAppointment($driver, [
        'appointment' => ['email_addresses' => ['shipping@example.com']],
    ]);

    $this->actingAs($driver)
        ->post(route('account.history.appointment.cancel', $appointment->uuid), [
            'reason' => 'Running too late',
        ])
        ->assertSessionHasNoErrors();

    Mail::assertQueued(
        AppointmentCancelled::class,
        fn (AppointmentCancelled $mail): bool => $mail->hasTo('shipping@example.com'),
    );
});

it('does not mail a facility with no configured addresses', function (): void {
    Mail::fake();

    $driver = cancellingDriver();
    $appointment = cancellableAppointment($driver);

    $this->actingAs($driver)
        ->post(route('account.history.appointment.cancel', $appointment->uuid), [
            'reason' => 'Load cancelled',
        ])
        ->assertSessionHasNoErrors();

    Mail::assertNotQueued(AppointmentCancelled::class);
});

it('confirms the cancellation to the driver', function (): void {
    Notification::fake();

    $driver = cancellingDriver();
    $appointment = cancellableAppointment($driver);

    $this->actingAs($driver)
        ->post(route('account.history.appointment.cancel', $appointment->uuid), [
            'reason' => 'Load cancelled',
        ])
        ->assertSessionHasNoErrors();

    Notification::assertSentTo($driver, AppointmentCancellation::class);
});

it('confirms even when the driver has booking copies switched off', function (): void {
    Notification::fake();

    // The receipt for an action they just took is not the same thing as the
    // copies that ride along with a booking. See SendAppointmentCancellationAction.
    $driver = cancellingDriver(['notify_appointment_copy' => false]);
    $appointment = cancellableAppointment($driver);

    $this->actingAs($driver)
        ->post(route('account.history.appointment.cancel', $appointment->uuid), [
            'reason' => 'Load cancelled',
        ])
        ->assertSessionHasNoErrors();

    Notification::assertSentTo($driver, AppointmentCancellation::class);
});

/*
|--------------------------------------------------------------------------
| What is refused
|--------------------------------------------------------------------------
*/

it('requires a reason', function (): void {
    $driver = cancellingDriver();
    $appointment = cancellableAppointment($driver);

    $this->actingAs($driver)
        ->from(route('account.history.appointment', $appointment->uuid))
        ->post(route('account.history.appointment.cancel', $appointment->uuid), ['reason' => ''])
        ->assertSessionHasErrors('reason');

    expect($appointment->refresh()->status)->toBe(AppointmentStatus::Scheduled);
});

it('refuses a booking that has already passed', function (): void {
    $driver = cancellingDriver();
    $location = Location::factory()->appointmentsOnly()->create();

    $appointment = Appointment::factory()
        ->forUser($driver)
        ->atLocation($location)
        ->scheduledFor(Date::now()->subDay())
        ->create();

    $this->actingAs($driver)
        ->from(route('account.history.appointment', $appointment->uuid))
        ->post(route('account.history.appointment.cancel', $appointment->uuid), [
            'reason' => 'Rewriting history',
        ])
        ->assertSessionHasErrors('reason');

    expect($appointment->refresh()->status)->toBe(AppointmentStatus::Scheduled);
});

it('refuses a booking that is already cancelled', function (): void {
    $driver = cancellingDriver();
    $location = Location::factory()->appointmentsOnly()->create();

    $appointment = Appointment::factory()
        ->forUser($driver)
        ->atLocation($location)
        ->scheduledFor(Date::now()->addDays(2))
        ->cancelled('Already gone')
        ->create();

    $this->actingAs($driver)
        ->from(route('account.history.appointment', $appointment->uuid))
        ->post(route('account.history.appointment.cancel', $appointment->uuid), [
            'reason' => 'Cancelling twice',
        ])
        ->assertSessionHasErrors('reason');

    expect($appointment->refresh()->cancelled_reason)->toBe('Already gone');
});

it('404s on a booking belonging to someone else', function (): void {
    $owner = cancellingDriver();
    $stranger = cancellingDriver(['email' => 'stranger@example.com']);
    $appointment = cancellableAppointment($owner);

    $this->actingAs($stranger)
        ->post(route('account.history.appointment.cancel', $appointment->uuid), [
            'reason' => 'Not mine to cancel',
        ])
        ->assertNotFound();

    expect($appointment->refresh()->status)->toBe(AppointmentStatus::Scheduled);
});

it('404s on an unclaimed guest booking', function (): void {
    $driver = cancellingDriver();
    $location = Location::factory()->appointmentsOnly()->create();

    $appointment = Appointment::factory()
        ->atLocation($location)
        ->scheduledFor(Date::now()->addDays(2))
        ->create(['user_id' => null]);

    $this->actingAs($driver)
        ->post(route('account.history.appointment.cancel', $appointment->uuid), [
            'reason' => 'Guest booking',
        ])
        ->assertNotFound();
});

it('redirects guests to the login page', function (): void {
    $driver = cancellingDriver();
    $appointment = cancellableAppointment($driver);

    $this->post(route('account.history.appointment.cancel', $appointment->uuid), [
        'reason' => 'Not signed in',
    ])->assertRedirect(route('login'));
});

it('sends an unverified driver to the verification notice', function (): void {
    $driver = cancellingDriver(['email_verified_at' => null]);
    $appointment = cancellableAppointment($driver);

    $this->actingAs($driver)
        ->post(route('account.history.appointment.cancel', $appointment->uuid), [
            'reason' => 'Not verified',
        ])
        ->assertRedirect(route('verification.notice'));
});
