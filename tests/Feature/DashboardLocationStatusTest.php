<?php

declare(strict_types=1);

use App\Enums\RecordHistoryEvent;
use App\Enums\Role;
use App\Enums\UserHistoryEvent;
use App\Models\Location;
use App\Models\User;
use App\Models\UserHistory;
use Inertia\Testing\AssertableInertia;

/*
 * Admins turning a location, or its check-ins or appointments, on or off.
 */

function statusAdmin(): User
{
    return User::factory()->withRole(Role::Admin)->create(['name' => 'Ada Admin']);
}

it('turns a flag off and logs it on both trails', function (string $field, string $message): void {
    $location = Location::factory()->create(['name' => 'Alpha Dock', 'is_appointments_enabled' => true]);

    $this->actingAs(statusAdmin())
        ->from(route('dashboard.locations.show', ['uuid' => $location->uuid]))
        ->patch(route('dashboard.locations.status.update', ['uuid' => $location->uuid]), [$field => false])
        ->assertRedirect(route('dashboard.locations.show', ['uuid' => $location->uuid]))
        ->assertInertiaFlash('toast.message', $message);

    $trail = UserHistory::query()->sole();

    expect($location->refresh()->getAttribute($field))->toBeFalse()
        ->and($location->history()->sole()->event)->toBe(RecordHistoryEvent::DetailsEdited)
        ->and($location->history()->sole()->context)->toBe([$field => ['from' => true, 'to' => false]])
        ->and($trail->event)->toBe(UserHistoryEvent::LocationEdited)
        ->and($trail->changes['location'])->toBe($location->uuid);
})->with([
    'active' => ['is_active', 'Alpha Dock is now inactive.'],
    'check-ins' => ['is_checkins_enabled', 'Check-ins at Alpha Dock are turned off.'],
    'appointments' => ['is_appointments_enabled', 'Appointments at Alpha Dock are turned off.'],
]);

it('turns a flag back on', function (): void {
    $location = Location::factory()->create(['name' => 'Alpha Dock', 'is_active' => false]);

    $this->actingAs(statusAdmin())
        ->patch(route('dashboard.locations.status.update', ['uuid' => $location->uuid]), ['is_active' => true])
        ->assertInertiaFlash('toast.message', 'Alpha Dock is now active.');

    expect($location->refresh()->is_active)->toBeTrue();
});

it('writes no history when the flag already has that value', function (): void {
    $location = Location::factory()->create();

    $this->actingAs(statusAdmin())
        ->patch(route('dashboard.locations.status.update', ['uuid' => $location->uuid]), ['is_active' => true])
        ->assertInertiaFlash('toast.message', 'Nothing changed.');

    expect($location->history()->count())->toBe(0)
        ->and(UserHistory::query()->count())->toBe(0);
});

it('shows the change on the location history in words', function (): void {
    $location = Location::factory()->create();
    $admin = statusAdmin();

    $this->actingAs($admin)->patch(route('dashboard.locations.status.update', ['uuid' => $location->uuid]), ['is_active' => false]);
    $this->actingAs($admin)->patch(route('dashboard.locations.status.update', ['uuid' => $location->uuid]), ['is_checkins_enabled' => false]);

    $this->get(route('dashboard.locations.history', ['uuid' => $location->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('history.data.0.changes.0.label', 'Check-ins')
            ->where('history.data.0.changes.0.from', 'On')
            ->where('history.data.0.changes.0.to', 'Off')
            ->where('history.data.1.changes.0.label', 'Status')
            ->where('history.data.1.changes.0.from', 'Active')
            ->where('history.data.1.changes.0.to', 'Inactive'));
});

it('requires at least one flag, as a boolean', function (array $payload, string $field): void {
    $location = Location::factory()->create();

    $this->actingAs(statusAdmin())
        ->patch(route('dashboard.locations.status.update', ['uuid' => $location->uuid]), $payload)
        ->assertSessionHasErrors($field);

    expect($location->history()->count())->toBe(0);
})->with([
    'empty' => [[], 'is_active'],
    'not a boolean' => [['is_active' => 'nope'], 'is_active'],
]);

it('rejects fields outside the status flags', function (): void {
    $location = Location::factory()->create(['name' => 'Alpha Dock']);

    $this->actingAs(statusAdmin())
        ->patch(route('dashboard.locations.status.update', ['uuid' => $location->uuid]), ['is_active' => false, 'name' => 'Beta'])
        ->assertSessionHasErrors('name');

    expect($location->refresh()->is_active)->toBeTrue()
        ->and($location->name)->toBe('Alpha Dock');
});

it('forbids staff without manage location status', function (): void {
    $location = Location::factory()->create();
    $manager = User::factory()->withRole(Role::ShippingManager)->worksAt($location)->create();

    $this->actingAs($manager)
        ->patch(route('dashboard.locations.status.update', ['uuid' => $location->uuid]), ['is_active' => false])
        ->assertForbidden();

    expect($location->refresh()->is_active)->toBeTrue();
});
