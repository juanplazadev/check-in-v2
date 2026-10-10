<?php

declare(strict_types=1);

use App\Enums\NotificationChannel;
use App\Enums\Role;
use App\Enums\Theme;
use App\Models\Location;
use App\Models\User;

use function Pest\Laravel\patch;

function updateDriver(): User
{
    return User::query()->create([
        'name' => 'John Driver',
        'email' => 'driver@example.com',
        'password' => 'secret-password',
        'email_verified_at' => now(),
    ])->assignRole(Role::Driver);
}

function bookableLocation(): Location
{
    return Location::factory()->create([
        'is_active' => true,
        'is_appointments_enabled' => true,
    ]);
}

it('redirects guests to the login page', function (): void {
    patch(route('account.settings.update'), ['theme' => 'dark'])
        ->assertRedirect(route('login'));
});

it('saves the theme', function (string $theme): void {
    $driver = updateDriver();

    $this->actingAs($driver)
        ->from(route('account.settings'))
        ->patch(route('account.settings.update'), ['theme' => $theme])
        ->assertRedirect(route('account.settings'))
        ->assertSessionHasNoErrors();

    expect($driver->refresh()->theme)->toBe(Theme::from($theme));
})->with(['light', 'dark', 'system']);

it('clears the theme back to null', function (): void {
    $driver = updateDriver();
    $driver->forceFill(['theme' => Theme::Dark])->save();

    $this->actingAs($driver)
        ->patch(route('account.settings.update'), ['theme' => null])
        ->assertSessionHasNoErrors();

    expect($driver->refresh()->theme)->toBeNull();
});

it('rejects a theme that is not one of the three', function (): void {
    $driver = updateDriver();

    $this->actingAs($driver)
        ->patch(route('account.settings.update'), ['theme' => 'sepia'])
        ->assertSessionHasErrors('theme');

    expect($driver->refresh()->theme)->toBeNull();
});

it('saves the usual location by uuid, storing the primary key', function (): void {
    $driver = updateDriver();
    $location = bookableLocation();

    $this->actingAs($driver)
        ->patch(route('account.settings.update'), ['location_id' => $location->uuid])
        ->assertSessionHasNoErrors();

    expect($driver->refresh()->location_id)->toBe($location->id);
});

it('clears the usual location', function (): void {
    $location = bookableLocation();
    $driver = updateDriver();
    $driver->forceFill(['location_id' => $location->id])->save();

    $this->actingAs($driver)
        ->patch(route('account.settings.update'), ['location_id' => null])
        ->assertSessionHasNoErrors();

    expect($driver->refresh()->location_id)->toBeNull();
});

it('refuses a location the driver could not have booked at', function (array $attributes): void {
    $driver = updateDriver();
    $location = Location::factory()->create($attributes);

    $this->actingAs($driver)
        ->patch(route('account.settings.update'), ['location_id' => $location->uuid])
        ->assertSessionHasErrors('location_id');

    expect($driver->refresh()->location_id)->toBeNull();
})->with([
    'inactive' => [['is_active' => false, 'is_appointments_enabled' => true]],
    'appointments disabled' => [['is_active' => true, 'is_appointments_enabled' => false]],
]);

it('refuses a uuid that matches no location at all', function (): void {
    $driver = updateDriver();

    $this->actingAs($driver)
        ->patch(route('account.settings.update'), ['location_id' => fake()->uuid()])
        ->assertSessionHasErrors('location_id');

    expect($driver->refresh()->location_id)->toBeNull();
});

it('rejects a location id that is not a uuid, so the primary key is never accepted', function (): void {
    $driver = updateDriver();
    $location = bookableLocation();

    $this->actingAs($driver)
        ->patch(route('account.settings.update'), ['location_id' => $location->id])
        ->assertSessionHasErrors('location_id');

    expect($driver->refresh()->location_id)->toBeNull();
});

/*
 * Each control on the settings page saves on its own, so a payload carrying one field must not wipe
 * the others back to their defaults.
 */
it('leaves untouched preferences alone', function (): void {
    $location = bookableLocation();
    $driver = updateDriver();
    $driver->forceFill([
        'theme' => Theme::Dark,
        'location_id' => $location->id,
    ])->save();

    $this->actingAs($driver)
        ->patch(route('account.settings.update'), ['theme' => 'light'])
        ->assertSessionHasNoErrors();

    $driver->refresh();

    expect($driver->theme)->toBe(Theme::Light)
        ->and($driver->location_id)->toBe($location->id);

    $this->actingAs($driver)
        ->patch(route('account.settings.update'), ['location_id' => null])
        ->assertSessionHasNoErrors();

    $driver->refresh();

    expect($driver->location_id)->toBeNull()
        ->and($driver->theme)->toBe(Theme::Light);
});

it('saves each notification toggle on its own', function (string $field): void {
    $driver = updateDriver();

    expect($driver->{$field})->toBeTrue();

    $this->actingAs($driver)
        ->patch(route('account.settings.update'), [$field => false])
        ->assertSessionHasNoErrors();

    expect($driver->refresh()->{$field})->toBeFalse();

    $this->actingAs($driver)
        ->patch(route('account.settings.update'), [$field => true])
        ->assertSessionHasNoErrors();

    expect($driver->refresh()->{$field})->toBeTrue();
})->with([
    'check-in copy' => ['notify_check_in_copy'],
    'appointment copy' => ['notify_appointment_copy'],
    'appointment reminder' => ['notify_appointment_reminder'],
]);

it('saves the notification channel', function (string $channel): void {
    $driver = updateDriver();

    $this->actingAs($driver)
        ->patch(route('account.settings.update'), ['notification_channel' => $channel])
        ->assertSessionHasNoErrors();

    expect($driver->refresh()->notification_channel)->toBe(NotificationChannel::from($channel));
})->with(['email', 'sms', 'both']);

it('rejects a channel that is not one of the three', function (): void {
    $driver = updateDriver();

    $this->actingAs($driver)
        ->patch(route('account.settings.update'), ['notification_channel' => 'carrier-pigeon'])
        ->assertSessionHasErrors('notification_channel');

    expect($driver->refresh()->notification_channel)->toBe(NotificationChannel::Email);
});

/*
 * Toggling one preference must not reset the others, the same guarantee the
 * theme and location controls already rely on.
 */
it('leaves the other notification preferences alone', function (): void {
    $driver = updateDriver();
    $driver->forceFill([
        'notify_appointment_copy' => false,
        'notification_channel' => NotificationChannel::Both,
    ])->save();

    $this->actingAs($driver)
        ->patch(route('account.settings.update'), ['notify_check_in_copy' => false])
        ->assertSessionHasNoErrors();

    $driver->refresh();

    expect($driver->notify_check_in_copy)->toBeFalse()
        ->and($driver->notify_appointment_copy)->toBeFalse()
        ->and($driver->notify_appointment_reminder)->toBeTrue()
        ->and($driver->notification_channel)->toBe(NotificationChannel::Both);
});
