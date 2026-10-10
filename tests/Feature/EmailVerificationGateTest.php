<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\CheckIn;
use App\Models\Location;
use App\Models\User;
use App\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\get;

/**
 * User has no HasFactory trait, so the suite builds drivers by hand throughout.
 */
function unverifiedDriver(): User
{
    return User::query()->create([
        'name' => 'John Driver',
        'email' => 'unverified@example.com',
        'password' => 'secret-password',
    ])->assignRole(Role::Driver);
}

function verifiedDriver(): User
{
    $driver = unverifiedDriver();
    $driver->forceFill(['email_verified_at' => now()])->save();

    return $driver;
}

/*
|--------------------------------------------------------------------------
| The gate
|--------------------------------------------------------------------------
*/

it('sends an unverified driver from the account overview to the notice', function (): void {
    $this->actingAs(unverifiedDriver())
        ->get(route('account'))
        ->assertRedirect(route('verification.notice'));
});

it('gates the rest of the account surface', function (string $route): void {
    $this->actingAs(unverifiedDriver())
        ->get(route($route))
        ->assertRedirect(route('verification.notice'));
})->with([
    'account.history',
    'account.settings',
]);

it('gates a history record and its pdf', function (): void {
    $driver = unverifiedDriver();
    $checkIn = CheckIn::factory()->create([
        'location_id' => Location::factory()->create()->id,
        'user_id' => $driver->id,
    ]);

    $this->actingAs($driver)
        ->get(route('account.history.checkIn', $checkIn->uuid))
        ->assertRedirect(route('verification.notice'));

    $this->actingAs($driver)
        ->get(route('account.history.checkIn.pdf', $checkIn->uuid))
        ->assertRedirect(route('verification.notice'));
});

it('gates the settings update', function (): void {
    $this->actingAs(unverifiedDriver())
        ->patch(route('account.settings.update'), ['theme' => 'dark'])
        ->assertRedirect(route('verification.notice'));
});

it('lets a verified driver through', function (): void {
    $this->actingAs(verifiedDriver())
        ->get(route('account'))
        ->assertOk();
});

/*
|--------------------------------------------------------------------------
| The escape corridor
|--------------------------------------------------------------------------
|
| A driver who mistyped their email on registration can only get out of it
| through these. If any of them ever picks up the "verified" middleware, the
| account becomes unrecoverable without support.
|
*/

it('leaves the profile page open while unverified', function (): void {
    // Password confirmation was added to this page later. It adds a step to the
    // corridor but must not close it, which PasswordConfirmationTest walks end
    // to end; here we only care that "verified" is not what is blocking.
    $this->actingAs(unverifiedDriver());
    confirmPassword();

    $this->get(route('account.profile'))->assertOk();
});

it('lets an unverified driver correct their email and re-sends the link', function (): void {
    Notification::fake();

    $driver = unverifiedDriver();

    $this->actingAs($driver);
    confirmPassword();

    $this
        ->from(route('account.profile'))
        ->put('/user/profile-information', [
            'name' => $driver->name,
            'email' => 'corrected@example.com',
            'cellphone' => null,
        ])
        ->assertSessionHasNoErrors();

    $driver->refresh();

    expect($driver->email)->toBe('corrected@example.com')
        ->and($driver->email_verified_at)->toBeNull();

    Notification::assertSentTo($driver, VerifyEmail::class);
});

it('lets an unverified driver change their password', function (): void {
    $this->actingAs(unverifiedDriver())
        ->from(route('account.profile'))
        ->put('/user/password', [
            'current_password' => 'secret-password',
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])
        ->assertSessionHasNoErrors();
});

it('lets an unverified driver delete their account', function (): void {
    $driver = unverifiedDriver();

    $this->actingAs($driver)
        ->from(route('account.profile'))
        ->delete(route('account.destroy'), ['password' => 'secret-password'])
        ->assertRedirect(route('home'));

    expect(User::query()->whereKey($driver->id)->exists())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| The notice page
|--------------------------------------------------------------------------
*/

it('renders the notice for an unverified driver', function (): void {
    $this->actingAs(unverifiedDriver())
        ->get(route('verification.notice'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/VerifyEmail'));
});

it('bounces a verified driver off the notice', function (): void {
    $this->actingAs(verifiedDriver())
        ->get(route('verification.notice'))
        ->assertRedirect(route('account'));
});

it('redirects guests from the notice to the login page', function (): void {
    get(route('verification.notice'))->assertRedirect(route('login'));
});

it('resends the verification link', function (): void {
    Notification::fake();

    $driver = unverifiedDriver();

    $this->actingAs($driver)
        ->from(route('verification.notice'))
        ->post('/email/verification-notification')
        ->assertSessionHas('status', 'verification-link-sent');

    Notification::assertSentTo($driver, VerifyEmail::class);
});

/*
|--------------------------------------------------------------------------
| Guest flows stay open
|--------------------------------------------------------------------------
|
| Check-in and appointment booking are the product; they must never require
| an account, let alone a verified one.
|
*/

it('leaves the guest check-in and appointment entry points open', function (string $route): void {
    get(route($route))->assertOk();
})->with([
    'checkIn.selectLocation',
    'appointment.selectLocation',
]);

it('does not gate the guest flows for a signed-in unverified driver', function (): void {
    $this->actingAs(unverifiedDriver())
        ->get(route('checkIn.selectLocation'))
        ->assertOk();
});
