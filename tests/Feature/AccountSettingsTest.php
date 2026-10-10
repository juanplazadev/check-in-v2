<?php

declare(strict_types=1);

use App\Enums\NotificationChannel;
use App\Enums\Role;
use App\Models\Location;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\get;

function settingsDriver(array $attributes = []): User
{
    return User::query()->create([
        'name' => 'John Driver',
        'email' => 'driver@example.com',
        'password' => 'secret-password',
        'email_verified_at' => now(),
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

it('redirects guests to the login page', function (): void {
    get(route('account.settings'))->assertRedirect(route('login'));
});

it('renders the settings page for an authenticated driver', function (): void {
    $this->actingAs(settingsDriver())
        ->get(route('account.settings'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Account/Settings')
            ->has('locations')
            ->has('hasCellphone'));
});

/*
 * Bookable locations, not checkable-in ones: the preference only steers the appointment flow, so
 * offering a location that the appointment picker never lists would be a dead choice.
 */
it('lists only active appointment locations for the usual-location picker', function (): void {
    $enabled = Location::factory()->create([
        'is_active' => true,
        'is_appointments_enabled' => true,
    ]);

    Location::factory()->create([
        'is_active' => false,
        'is_appointments_enabled' => true,
    ]);

    Location::factory()->create([
        'is_active' => true,
        'is_appointments_enabled' => false,
        'is_checkins_enabled' => true,
    ]);

    $this->actingAs(settingsDriver())
        ->get(route('account.settings'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('locations', 1)
            ->where('locations.0.id', $enabled->uuid)
            ->where('locations.0.name', $enabled->name));
});

it('passes the stored usual location back as a uuid', function (): void {
    $location = Location::factory()->create([
        'is_active' => true,
        'is_appointments_enabled' => true,
    ]);

    $driver = settingsDriver();
    $driver->forceFill(['location_id' => $location->id])->save();

    $this->actingAs($driver)
        ->get(route('account.settings'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('locationId', $location->uuid));
});

it('reports no usual location once the stored one stops being bookable', function (): void {
    $location = Location::factory()->create([
        'is_active' => true,
        'is_appointments_enabled' => false,
    ]);

    $driver = settingsDriver();
    $driver->forceFill(['location_id' => $location->id])->save();

    $this->actingAs($driver)
        ->get(route('account.settings'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('locationId', null));
});

it('keeps the raw location foreign key out of the shared auth user', function (): void {
    $location = Location::factory()->create([
        'is_active' => true,
        'is_appointments_enabled' => true,
    ]);

    $driver = settingsDriver();
    $driver->forceFill(['location_id' => $location->id])->save();

    $this->actingAs($driver)
        ->get(route('account.settings'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->missing('auth.user.location_id'));
});

it('reports whether a phone number is on file so the text-message option can be gated', function (): void {
    $this->actingAs(settingsDriver())
        ->get(route('account.settings'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('hasCellphone', false));

    $this->actingAs(settingsDriver([
        'email' => 'withphone@example.com',
        'cellphone' => '+15551234567',
    ]))
        ->get(route('account.settings'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('hasCellphone', true));
});

/*
 * All three toggles default on and the channel defaults to email, so a driver
 * who has never opened this page still receives their copies.
 */
it('passes the notification preferences, defaulting to everything on by email', function (): void {
    $this->actingAs(settingsDriver())
        ->get(route('account.settings'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('notifications.checkInCopy', true)
            ->where('notifications.appointmentCopy', true)
            ->where('notifications.appointmentReminder', true)
            ->where('notifications.channel', 'email'));
});

it('passes the stored notification preferences back', function (): void {
    $driver = settingsDriver();
    $driver->forceFill([
        'notify_check_in_copy' => false,
        'notification_channel' => NotificationChannel::Both,
    ])->save();

    $this->actingAs($driver)
        ->get(route('account.settings'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('notifications.checkInCopy', false)
            ->where('notifications.appointmentCopy', true)
            ->where('notifications.channel', 'both'));
});

it('ships the settings translations and nav label in every locale', function (string $locale, string $settingsLabel): void {
    $this->actingAs(settingsDriver())
        ->withSession(['locale' => $locale])
        ->get(route('account.settings'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('translations.accountSettings.title')
            ->has('translations.accountSettings.appointmentCopyLabel')
            ->has('translations.accountSettings.appointmentCopyDescription')
            ->has('translations.accountSettings.copiesAlwaysEmail')
            ->has('translations.accountSettings.securityAlwaysEmail')
            ->has('translations.accountSettings.deleteDialogConfirm')
            ->where('translations.accountNav.settings', $settingsLabel)
            ->where('translations.publicNavigation.settings', $settingsLabel));
})->with([
    'english' => ['en', 'Settings'],
    'spanish' => ['es', 'Ajustes'],
    'french' => ['fr', 'Réglages'],
]);

it('shares hasLicense with every account page so the header chip never vanishes', function (string $routeName): void {
    $driver = settingsDriver(['drivers_license_number' => 'D1234567']);

    $this->actingAs($driver)
        ->get(route($routeName))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('hasLicense', true));
})->with(['account', 'account.settings', 'account.history', 'account.profile']);

it('does not share hasLicense outside the account area', function (): void {
    $this->actingAs(settingsDriver())
        ->get(route('home'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('hasLicense', null));
});

it('never leaks the encrypted licence number to the settings page', function (): void {
    $driver = settingsDriver(['drivers_license_number' => 'D1234567']);

    $response = $this->actingAs($driver)->get(route('account.settings'));

    expect($response->getContent())->not->toContain('D1234567');
});
