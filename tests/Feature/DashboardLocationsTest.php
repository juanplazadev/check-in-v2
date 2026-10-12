<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Address;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

/*
 * The location data behind the locations table and detail page. Gates
 * are pinned in DashboardPagesTest.
 */

function locationsAdmin(): User
{
    return User::factory()->withRole(Role::Admin)->create();
}

it('lists every location by name, leaving out deleted ones', function (): void {
    $admin = locationsAdmin();
    $zulu = Location::factory()->create(['name' => 'Zulu Dock', 'is_active' => false]);
    $alpha = Location::factory()->create(['name' => 'Alpha Dock']);
    Location::factory()->create(['name' => 'Mike Dock'])->delete();
    // An attachment the admin does not need: admins see every location.
    $alpha->users()->attach(User::factory()->withRole(Role::Shipping)->create());

    $this->actingAs($admin)
        ->get(route('dashboard.locations'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Dashboard/Locations/Index')
            ->has('locations', 2)
            ->where('locations.0.uuid', $alpha->uuid)
            ->where('locations.0.staffCount', 1)
            ->where('locations.1.uuid', $zulu->uuid)
            ->where('locations.1.isActive', false)
            ->missing('locations.0.id'));
});

it('sends the address, settings and staff of one location', function (): void {
    $location = Location::factory()->create([
        'address_id' => Address::factory()->create(['city' => 'Miami', 'state' => 'FL']),
        'max_distance_allowed' => 75,
    ]);
    $shipping = User::factory()->withRole(Role::Shipping)->create(['name' => 'Sam Shipping']);
    $location->users()->attach($shipping);

    $this->actingAs(locationsAdmin())
        ->get(route('dashboard.locations.show', ['uuid' => $location->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Dashboard/Locations/Show')
            ->where('location.uuid', $location->uuid)
            ->where('location.address.city', 'Miami')
            ->where('location.address.state', 'FL')
            ->where('location.maxDistanceAllowed', 75)
            ->where('location.staffCount', 1)
            ->has('location.appointmentBooking.slotIntervalMinutes')
            ->has('location.staff', 1)
            ->where('location.staff.0.uuid', $shipping->uuid)
            ->where('location.staff.0.roles', [Role::Shipping->value])
            ->missing('location.id'));
});

it('returns not found for an unknown or deleted location', function (Closure $uuid): void {
    $this->actingAs(locationsAdmin())
        ->get(route('dashboard.locations.show', ['uuid' => $uuid()]))
        ->assertNotFound();
})->with([
    'unknown' => [fn (): string => (string) Str::uuid()],
    'deleted' => [fn (): string => tap(Location::factory()->create())->delete()->uuid],
]);

it('shows location-scoped staff only the locations they work at', function (): void {
    $mine = Location::factory()->create();
    $other = Location::factory()->create();
    $shipping = User::factory()->withRole(Role::Shipping)->worksAt($mine)->create();

    $this->actingAs($shipping)
        ->get(route('dashboard.locations'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('locations', 1)
            ->where('locations.0.uuid', $mine->uuid));

    $this->get(route('dashboard.locations.show', ['uuid' => $other->uuid]))
        ->assertNotFound();
});
