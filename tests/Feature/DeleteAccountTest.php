<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Enums\RecordHistoryEvent;
use App\Enums\Role;
use App\Enums\UserHistoryEvent;
use App\Models\Appointment;
use App\Models\CheckIn;
use App\Models\RecordHistory;
use App\Models\User;
use App\Models\UserHistory;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\delete;

/**
 * @param  array<string, mixed>  $attributes
 */
function deletableDriver(array $attributes = []): User
{
    return User::query()->create([
        'name' => 'John Driver',
        'email' => 'deletable@example.com',
        'password' => 'secret-password',
        ...$attributes,
    ])->assignRole(Role::Driver);
}

it('redirects guests to the login page', function (): void {
    delete(route('account.destroy'), ['password' => 'secret-password'])
        ->assertRedirect(route('login'));
});

it('refuses the wrong password and leaves the account standing', function (): void {
    $driver = deletableDriver();

    $this->actingAs($driver)
        ->from(route('account.settings'))
        ->delete(route('account.destroy'), ['password' => 'not-my-password'])
        ->assertRedirect(route('account.settings'))
        ->assertSessionHasErrors('password');

    expect(User::query()->whereKey($driver->id)->exists())->toBeTrue();
});

it('requires a password at all', function (): void {
    $driver = deletableDriver();

    $this->actingAs($driver)
        ->delete(route('account.destroy'), [])
        ->assertSessionHasErrors('password');

    expect(User::query()->whereKey($driver->id)->exists())->toBeTrue();
});

it('deletes the account and sends the driver home', function (): void {
    $driver = deletableDriver();

    $this->actingAs($driver)
        ->delete(route('account.destroy'), ['password' => 'secret-password'])
        ->assertRedirect(route('home'))
        ->assertSessionHasNoErrors();

    expect(User::query()->whereKey($driver->id)->exists())->toBeFalse()
        ->and(auth()->check())->toBeFalse();
});

it('frees the email address for a fresh signup', function (): void {
    $driver = deletableDriver();

    $this->actingAs($driver)
        ->delete(route('account.destroy'), ['password' => 'secret-password']);

    // The whole reason for hard deleting rather than soft: a retained row would
    // hold the unique index and lock the person out of their own address.
    expect(fn (): User => deletableDriver())->not->toThrow(Exception::class);
});

/*
 * The promise the delete dialog makes: "Your past check-ins and appointments
 * stay with the yard, but will no longer be linked to you."
 */
it('leaves past check-ins and bookings with the yard, unlinked', function (): void {
    $driver = deletableDriver();
    $checkIn = CheckIn::factory()->forUser($driver)->create();
    $appointment = Appointment::factory()->forUser($driver)->create();

    $this->actingAs($driver)
        ->delete(route('account.destroy'), ['password' => 'secret-password']);

    $checkIn->refresh();
    $appointment->refresh();

    expect($checkIn->exists)->toBeTrue()
        ->and($checkIn->user_id)->toBeNull()
        ->and($checkIn->reference_number)->not->toBeNull()
        ->and($appointment->exists)->toBeTrue()
        ->and($appointment->user_id)->toBeNull();
});

/*
 * The yard is still expecting that truck in that slot. Cancelling it silently
 * would strand their schedule.
 */
it('leaves an upcoming appointment standing', function (): void {
    $driver = deletableDriver();
    $appointment = Appointment::factory()
        ->forUser($driver)
        ->scheduled()
        ->scheduledFor(now()->addDays(2))
        ->create();

    $this->actingAs($driver)
        ->delete(route('account.destroy'), ['password' => 'secret-password']);

    expect($appointment->refresh()->status)->toBe(AppointmentStatus::Scheduled);
});

it('takes the account trail with the account', function (): void {
    $driver = deletableDriver();
    UserHistory::query()->create([
        'user_id' => $driver->id,
        'event' => UserHistoryEvent::ProfileUpdated,
    ]);

    $this->actingAs($driver)
        ->delete(route('account.destroy'), ['password' => 'secret-password']);

    expect(UserHistory::query()->where('user_id', $driver->id)->exists())->toBeFalse();
});

/*
 * The opposite of the account trail: the record trail belongs to the yard, so it
 * survives with the driver reference dropped.
 */
it('keeps the record trail and marks the deletion on each record', function (): void {
    $driver = deletableDriver();
    $checkIn = CheckIn::factory()->forUser($driver)->create();
    $appointment = Appointment::factory()->forUser($driver)->create();

    $this->actingAs($driver)
        ->delete(route('account.destroy'), ['password' => 'secret-password']);

    expect($checkIn->history()->where('event', RecordHistoryEvent::AccountDeleted)->count())->toBe(1)
        ->and($appointment->history()->where('event', RecordHistoryEvent::AccountDeleted)->count())->toBe(1)
        ->and(RecordHistory::query()->whereNotNull('user_id')->count())->toBe(0);
});

/*
 * The suite runs on the array session driver, which would skip the purge
 * entirely and leave this path unexercised - the same blind spot that has hidden
 * session and cache bugs in this app before. Pinned to the driver production
 * actually uses so the real branch runs.
 */
it('purges every session the driver had open', function (): void {
    config(['session.driver' => 'database']);

    $driver = deletableDriver();

    DB::table('sessions')->insert([
        'id' => 'another-device',
        'user_id' => $driver->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'phpunit',
        'payload' => '',
        'last_activity' => now()->getTimestamp(),
    ]);

    $this->actingAs($driver)
        ->delete(route('account.destroy'), ['password' => 'secret-password']);

    // sessions.user_id has no foreign key, so nothing but this cleans it up.
    expect(DB::table('sessions')->where('user_id', $driver->id)->count())->toBe(0);
});

it('cannot sign in afterwards', function (): void {
    $driver = deletableDriver();

    $this->actingAs($driver)
        ->delete(route('account.destroy'), ['password' => 'secret-password']);

    $this->post(route('login.store'), [
        'email' => 'deletable@example.com',
        'password' => 'secret-password',
    ])->assertSessionHasErrors();

    expect(auth()->check())->toBeFalse();
});

it('leaves another driver and their records untouched', function (): void {
    $driver = deletableDriver();
    $other = deletableDriver(['email' => 'other@example.com']);
    $otherCheckIn = CheckIn::factory()->forUser($other)->create();

    $this->actingAs($driver)
        ->delete(route('account.destroy'), ['password' => 'secret-password']);

    expect(User::query()->whereKey($other->id)->exists())->toBeTrue()
        ->and($otherCheckIn->refresh()->user_id)->toBe($other->id)
        ->and($otherCheckIn->history()->where('event', RecordHistoryEvent::AccountDeleted)->count())->toBe(0);
});

/*
 * Only the database driver keeps sessions in a table we can query. On any other
 * driver the delete would hit a table that need not exist, so it is skipped -
 * and the account deletion itself must still go through.
 */
it('still deletes the account when sessions are not stored in the database', function (): void {
    config(['session.driver' => 'array']);

    $driver = deletableDriver();

    $this->actingAs($driver)
        ->delete(route('account.destroy'), ['password' => 'secret-password'])
        ->assertRedirect(route('home'))
        ->assertSessionHasNoErrors();

    expect(User::query()->whereKey($driver->id)->exists())->toBeFalse();
});
