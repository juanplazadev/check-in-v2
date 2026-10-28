<?php

declare(strict_types=1);

use App\Enums\RecordHistoryEvent;
use App\Enums\Role;
use App\Enums\UserHistoryEvent;
use App\Models\Appointment;
use App\Models\CheckIn;
use App\Models\Location;
use App\Models\User;
use App\Models\UserHistory;
use Inertia\Testing\AssertableInertia;

/*
 * Admins soft-deleting a location from its overview, and restoring it from
 * the locations table.
 */

function deleteAdmin(): User
{
    return User::factory()->withRole(Role::Admin)->create();
}

it('soft-deletes a location and logs the reason on both trails', function (): void {
    $location = Location::factory()->create(['name' => 'Alpha Dock']);

    $this->actingAs(deleteAdmin())
        ->delete(route('dashboard.locations.destroy', ['uuid' => $location->uuid]), ['reason' => 'Site closed'])
        ->assertRedirect(route('dashboard.locations'))
        ->assertInertiaFlash('toast.message', 'Alpha Dock was deleted.');

    $entry = $location->history()->sole();
    $trail = UserHistory::query()->sole();

    expect($location->refresh()->trashed())->toBeTrue()
        ->and($entry->event)->toBe(RecordHistoryEvent::LocationDeleted)
        ->and($entry->context)->toBe(['reason' => 'Site closed'])
        ->and($trail->event)->toBe(UserHistoryEvent::LocationDeleted)
        ->and($trail->changes['location'])->toBe($location->uuid)
        ->and($trail->changes['reason'])->toBe('Site closed');
});

it('requires a reason', function (): void {
    $location = Location::factory()->create();

    $this->actingAs(deleteAdmin())
        ->delete(route('dashboard.locations.destroy', ['uuid' => $location->uuid]), ['reason' => ' '])
        ->assertSessionHasErrors('reason');

    expect($location->refresh()->trashed())->toBeFalse();
});

it('refuses while a check-in is pending or an appointment is still to come', function (Closure $work): void {
    $location = Location::factory()->create();
    $work($location);

    $this->actingAs(deleteAdmin())
        ->delete(route('dashboard.locations.destroy', ['uuid' => $location->uuid]), ['reason' => 'Site closed'])
        ->assertSessionHasErrors('location');

    expect($location->refresh()->trashed())->toBeFalse()
        ->and($location->history()->count())->toBe(0);
})->with([
    'pending check-in' => [fn (Location $location) => CheckIn::factory()->pending()->atLocation($location)->create()],
    'upcoming appointment' => [fn (Location $location) => Appointment::factory()->scheduled()->atLocation($location)->scheduledFor(now()->addDay())->create()],
]);

it('allows finished check-ins and past or canceled appointments', function (): void {
    $location = Location::factory()->create();
    CheckIn::factory()->completed()->atLocation($location)->create();
    Appointment::factory()->scheduled()->atLocation($location)->scheduledFor(now()->subDay())->create();
    Appointment::factory()->cancelled()->atLocation($location)->scheduledFor(now()->addDay())->create();

    $this->actingAs(deleteAdmin())
        ->delete(route('dashboard.locations.destroy', ['uuid' => $location->uuid]), ['reason' => 'Site closed'])
        ->assertRedirect(route('dashboard.locations'));

    expect($location->refresh()->trashed())->toBeTrue();
});

it('keeps showing the location on its past check-ins', function (): void {
    $location = Location::factory()->create(['name' => 'Alpha Dock']);
    $checkIn = CheckIn::factory()->completed()->atLocation($location)->create();
    $admin = deleteAdmin();

    $this->actingAs($admin)
        ->delete(route('dashboard.locations.destroy', ['uuid' => $location->uuid]), ['reason' => 'Site closed']);

    expect($checkIn->refresh()->location?->name)->toBe('Alpha Dock');

    $this->get(route('dashboard.checkIns.show', ['uuid' => $checkIn->uuid]))->assertOk();
});

it('restores a deleted location', function (): void {
    $location = Location::factory()->create(['name' => 'Alpha Dock']);
    $location->delete();

    $this->actingAs(deleteAdmin())
        ->post(route('dashboard.locations.restore', ['uuid' => $location->uuid]))
        ->assertInertiaFlash('toast.message', 'Alpha Dock was restored.');

    expect($location->refresh()->trashed())->toBeFalse()
        ->and($location->history()->sole()->event)->toBe(RecordHistoryEvent::LocationRestored)
        ->and(UserHistory::query()->sole()->event)->toBe(UserHistoryEvent::LocationRestored);
});

it('404s restoring a location that is not deleted', function (): void {
    $location = Location::factory()->create();

    $this->actingAs(deleteAdmin())
        ->post(route('dashboard.locations.restore', ['uuid' => $location->uuid]))
        ->assertNotFound();
});

it('forbids staff other than admins', function (Role $role): void {
    $location = Location::factory()->create();
    $deleted = Location::factory()->create();
    $deleted->delete();

    $user = User::factory()->withRole($role)->worksAt($location)->create();

    $this->actingAs($user)
        ->delete(route('dashboard.locations.destroy', ['uuid' => $location->uuid]), ['reason' => 'Site closed'])
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('dashboard.locations.restore', ['uuid' => $deleted->uuid]))
        ->assertForbidden();

    expect($location->refresh()->trashed())->toBeFalse()
        ->and($deleted->refresh()->trashed())->toBeTrue();
})->with([Role::ShippingManager, Role::Shipping, Role::Sales]);

it('lists deleted locations on the locations table for admins only', function (): void {
    $location = Location::factory()->create(['name' => 'Alpha Dock']);
    $location->delete();

    $this->actingAs(deleteAdmin())
        ->get(route('dashboard.locations'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('deletedLocations', 1)
            ->where('deletedLocations.0.uuid', $location->uuid)
            ->where('deletedLocations.0.name', 'Alpha Dock'));

    $this->actingAs(User::factory()->withRole(Role::Sales)->create())
        ->get(route('dashboard.locations'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('deletedLocations', []));
});
