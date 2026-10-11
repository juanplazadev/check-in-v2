<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;
use App\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\get;

function makeDriver(array $attributes = []): User
{
    return User::query()->create([
        'name' => 'John Driver',
        'email' => 'john@example.com',
        'password' => 'secret-password',
        ...$attributes,
    ])->assignRole(Role::Driver);
}

/*
 * The profile page and the profile-update endpoint sit behind password
 * confirmation. These tests are about what the profile does, not about the
 * gate, so they satisfy it up front. PasswordConfirmationTest covers the gate.
 */
beforeEach(function (): void {
    confirmPassword();
});

it('redirects guests from the profile page', function (): void {
    get(route('account.profile'))->assertRedirect(route('login'));
});

it('renders the profile page for an authenticated user', function (): void {
    $this->actingAs(makeDriver())
        ->get(route('account.profile'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Account/EditProfile')
            ->where('hasLicense', false)
            ->has('translations.accountProfile.title')
            ->has('translations.accountNav.profile'));
});

it('updates the profile and normalizes the cellphone', function (): void {
    $user = makeDriver();

    $this->actingAs($user)
        ->put('/user/profile-information', [
            'name' => 'Johnny Driver',
            'email' => 'johnny@example.com',
            'cellphone' => '(201) 555-0123',
        ])
        ->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->name)->toBe('Johnny Driver')
        ->and($user->email)->toBe('johnny@example.com')
        ->and($user->cellphone)->toBe('+12015550123');
});

it('queues a verification email when the address changes', function (): void {
    Notification::fake();

    $user = makeDriver();

    $this->actingAs($user)
        ->put('/user/profile-information', [
            'name' => 'John Driver',
            'email' => 'changed@example.com',
            'cellphone' => '',
        ])
        ->assertSessionHasNoErrors();

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('clears the cellphone when submitted empty', function (): void {
    $user = makeDriver(['cellphone' => '+12015550123']);

    $this->actingAs($user)
        ->put('/user/profile-information', [
            'name' => 'John Driver',
            'email' => 'john@example.com',
            'cellphone' => '',
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->cellphone)->toBeNull();
});

it('rejects an invalid cellphone', function (): void {
    $user = makeDriver();

    $this->actingAs($user)
        ->put('/user/profile-information', [
            'name' => 'John Driver',
            'email' => 'john@example.com',
            'cellphone' => '12345',
        ])
        ->assertSessionHasErrors(['cellphone'], null, 'updateProfileInformation');

    expect($user->refresh()->cellphone)->toBeNull();
});

it('exposes the saved license to the profile page but hides the number from the shared auth user', function (): void {
    $user = makeDriver([
        'drivers_license_number' => 'D1234567',
        'drivers_license_state' => 'Texas',
        'drivers_license_country' => 'US',
        'drivers_license_expiration_date' => '2031-01-15',
    ]);

    $this->actingAs($user)
        ->get(route('account.profile'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('hasLicense', true)
            ->where('license.number', 'D1234567')
            ->where('license.state', 'Texas')
            ->where('license.country', 'US')
            ->where('license.expirationDate', '2031-01-15')
            ->missing('auth.user.drivers_license_number'));
});

it('saves the drivers license to the profile', function (): void {
    $user = makeDriver();

    $this->actingAs($user)
        ->put('/user/profile-information', [
            'name' => 'John Driver',
            'email' => 'john@example.com',
            'cellphone' => '',
            'drivers_license_number' => 'D1234567',
            'drivers_license_state' => 'Arizona',
            'drivers_license_country' => 'US',
            'drivers_license_expiration_date' => '2030-05-01',
        ])
        ->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->drivers_license_number)->toBe('D1234567')
        ->and($user->drivers_license_state)->toBe('Arizona')
        ->and($user->drivers_license_country)->toBe('US')
        ->and($user->drivers_license_expiration_date->format('Y-m-d'))->toBe('2030-05-01');
});

it('saves a license state from another supported country with its country code', function (): void {
    $user = makeDriver();

    $this->actingAs($user)
        ->put('/user/profile-information', [
            'name' => 'John Driver',
            'email' => 'john@example.com',
            'cellphone' => '',
            'drivers_license_state' => 'Sonora',
            'drivers_license_country' => 'mx',
        ])
        ->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->drivers_license_state)->toBe('Sonora')
        ->and($user->drivers_license_country)->toBe('MX');
});

it('rejects a license state that was typed without picking its country', function (): void {
    $user = makeDriver();

    $this->actingAs($user)
        ->put('/user/profile-information', [
            'name' => 'John Driver',
            'email' => 'john@example.com',
            'cellphone' => '',
            'drivers_license_state' => 'Arizona',
            'drivers_license_country' => '',
        ])
        ->assertSessionHasErrors(['drivers_license_country'], null, 'updateProfileInformation');

    expect($user->refresh()->drivers_license_state)->toBeNull();
});

it('rejects a license expiration date in the past', function (): void {
    $user = makeDriver();

    $this->actingAs($user)
        ->put('/user/profile-information', [
            'name' => 'John Driver',
            'email' => 'john@example.com',
            'cellphone' => '',
            'drivers_license_number' => 'D1234567',
            'drivers_license_state' => 'Arizona',
            'drivers_license_country' => 'US',
            'drivers_license_expiration_date' => now()->subDay()->format('Y-m-d'),
        ])
        ->assertSessionHasErrors(['drivers_license_expiration_date'], null, 'updateProfileInformation');
});

it('rejects a license expiration date more than 60 years out', function (): void {
    $user = makeDriver();

    $this->actingAs($user)
        ->put('/user/profile-information', [
            'name' => 'John Driver',
            'email' => 'john@example.com',
            'cellphone' => '',
            'drivers_license_number' => 'D1234567',
            'drivers_license_state' => 'Arizona',
            'drivers_license_country' => 'US',
            'drivers_license_expiration_date' => now()->addYears(61)->format('Y-m-d'),
        ])
        ->assertSessionHasErrors(['drivers_license_expiration_date'], null, 'updateProfileInformation');
});

it('accepts a far-future Arizona license within the 60 year window', function (): void {
    $user = makeDriver();

    $this->actingAs($user)
        ->put('/user/profile-information', [
            'name' => 'John Driver',
            'email' => 'john@example.com',
            'cellphone' => '',
            'drivers_license_number' => 'D1234567',
            'drivers_license_state' => 'Arizona',
            'drivers_license_country' => 'US',
            'drivers_license_expiration_date' => now()->addYears(45)->format('Y-m-d'),
        ])
        ->assertSessionHasNoErrors();
});

it('clears the license fields when submitted empty', function (): void {
    $user = makeDriver([
        'drivers_license_number' => 'D1234567',
        'drivers_license_state' => 'Arizona',
        'drivers_license_country' => 'US',
        'drivers_license_expiration_date' => '2030-05-01',
    ]);

    $this->actingAs($user)
        ->put('/user/profile-information', [
            'name' => 'John Driver',
            'email' => 'john@example.com',
            'cellphone' => '',
            'drivers_license_number' => '',
            'drivers_license_state' => '',
            'drivers_license_country' => 'US',
            'drivers_license_expiration_date' => '',
        ])
        ->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->drivers_license_number)->toBeNull()
        ->and($user->drivers_license_state)->toBeNull()
        ->and($user->drivers_license_country)->toBeNull()
        ->and($user->drivers_license_expiration_date)->toBeNull();
});

it('rejects a too-short license number', function (): void {
    $user = makeDriver();

    $this->actingAs($user)
        ->put('/user/profile-information', [
            'name' => 'John Driver',
            'email' => 'john@example.com',
            'cellphone' => '',
            'drivers_license_number' => 'AB',
        ])
        ->assertSessionHasErrors(['drivers_license_number'], null, 'updateProfileInformation');
});

it('updates the password', function (): void {
    $user = makeDriver();

    $this->actingAs($user)
        ->put('/user/password', [
            'current_password' => 'secret-password',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])
        ->assertSessionHasNoErrors();

    expect(Hash::check('NewPassword123!', $user->refresh()->password))->toBeTrue();
});
