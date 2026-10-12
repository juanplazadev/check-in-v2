<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;
use App\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\get;

/**
 * User has no HasFactory trait, so the suite builds drivers by hand throughout.
 *
 * @param  array<string, mixed>  $attributes
 */
function confirmingDriver(array $attributes = []): User
{
    return User::query()->create([
        'name' => 'John Driver',
        'email' => 'confirming@example.com',
        'password' => 'secret-password',
        'email_verified_at' => now(),
        ...$attributes,
    ])->assignRole(Role::Driver);
}

/** Marks the session as having confirmed a password just now. */
function withConfirmedPassword(): void
{
    session(['auth.password_confirmed_at' => Date::now()->unix()]);
}

/*
|--------------------------------------------------------------------------
| The gate
|--------------------------------------------------------------------------
*/

it('sends an unconfirmed driver from the profile page to the confirm screen', function (): void {
    $this->actingAs(confirmingDriver())
        ->get(route('account.profile'))
        ->assertRedirect(route('password.confirm'));
});

it('lets a confirmed driver onto the profile page', function (): void {
    $this->actingAs(confirmingDriver());
    withConfirmedPassword();

    get(route('account.profile'))->assertOk();
});

it('asks again once the confirmation has gone stale', function (): void {
    $this->actingAs(confirmingDriver());

    // Confirmed, but longer ago than config('auth.password_timeout').
    session([
        'auth.password_confirmed_at' => Date::now()->unix() - (int) config('auth.password_timeout') - 60,
    ]);

    get(route('account.profile'))->assertRedirect(route('password.confirm'));
});

it('renders the confirm screen', function (): void {
    $this->actingAs(confirmingDriver())
        ->get(route('password.confirm'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('Auth/ConfirmPassword'));
});

it('returns the driver to the page they were trying to reach', function (): void {
    $this->actingAs(confirmingDriver());

    // The middleware stores the intended URL via redirectGuest().
    get(route('account.profile'))->assertRedirect(route('password.confirm'));

    $this->post('/user/confirm-password', ['password' => 'secret-password'])
        ->assertRedirect(route('account.profile'));
});

it('rejects the wrong password and leaves the profile shut', function (): void {
    $this->actingAs(confirmingDriver())
        ->from(route('password.confirm'))
        ->post('/user/confirm-password', ['password' => 'not-my-password'])
        ->assertSessionHasErrors('password');

    get(route('account.profile'))->assertRedirect(route('password.confirm'));
});

it('redirects guests from the confirm screen to the login page', function (): void {
    get(route('password.confirm'))->assertRedirect(route('login'));
});

/*
|--------------------------------------------------------------------------
| The update endpoint
|--------------------------------------------------------------------------
|
| Fortify hardcodes ['auth'] on this route with no confirmPassword hook, so the
| check lives in UpdateUserProfileInformation. Gating only the page would leave
| a direct request from a hijacked session able to change the email, which is a
| complete account-takeover path via a password reset to the new address.
|
*/

it('refuses a profile update without a recent confirmation', function (): void {
    $driver = confirmingDriver();

    $this->actingAs($driver)
        ->from(route('account.profile'))
        ->put('/user/profile-information', [
            'name' => 'Attacker',
            'email' => 'attacker@example.com',
            'cellphone' => null,
        ])
        ->assertSessionHasErrors('password');

    expect($driver->refresh()->email)->toBe('confirming@example.com')
        ->and($driver->name)->toBe('John Driver');
});

it('allows a profile update once the password is confirmed', function (): void {
    Notification::fake();

    $driver = confirmingDriver();

    $this->actingAs($driver);
    withConfirmedPassword();

    $this->from(route('account.profile'))
        ->put('/user/profile-information', [
            'name' => 'John Driver',
            'email' => 'moved@example.com',
            'cellphone' => null,
        ])
        ->assertSessionHasNoErrors();

    expect($driver->refresh()->email)->toBe('moved@example.com');

    Notification::assertSentTo($driver, VerifyEmail::class);
});

/*
|--------------------------------------------------------------------------
| What stays open
|--------------------------------------------------------------------------
*/

it('leaves the settings page ungated', function (): void {
    // Theme, language, default location and notification toggles are all
    // reversible and disclose nothing, so a password prompt there would be
    // friction with no security return.
    $this->actingAs(confirmingDriver())
        ->get(route('account.settings'))
        ->assertOk();
});

it('leaves the settings update ungated', function (): void {
    $this->actingAs(confirmingDriver())
        ->from(route('account.settings'))
        ->patch(route('account.settings.update'), ['theme' => 'dark'])
        ->assertSessionHasNoErrors();
});

/*
 * The escape corridor from the email-verification work. An unverified driver
 * must still be able to reach the profile page and fix a mistyped address -
 * password confirmation may add a step, but it must not close the hatch.
 */
it('keeps the unverified escape corridor open end to end', function (): void {
    Notification::fake();

    $driver = confirmingDriver(['email_verified_at' => null]);

    $this->actingAs($driver);

    // Gated account surface still bounces them to the verification notice.
    get(route('account'))->assertRedirect(route('verification.notice'));

    // The profile hatch asks for a password rather than slamming shut...
    get(route('account.profile'))->assertRedirect(route('password.confirm'));

    // ...the confirm screen itself is reachable while unverified...
    get(route('password.confirm'))->assertOk();

    // ...and confirming lands them on the profile page, not back at the notice.
    $this->post('/user/confirm-password', ['password' => 'secret-password'])
        ->assertRedirect(route('account.profile'));

    get(route('account.profile'))->assertOk();

    // And they can actually correct the address.
    $this->put('/user/profile-information', [
        'name' => 'John Driver',
        'email' => 'corrected@example.com',
        'cellphone' => null,
    ])->assertSessionHasNoErrors();

    expect($driver->refresh()->email)->toBe('corrected@example.com');
});
