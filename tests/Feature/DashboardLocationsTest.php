<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Address;
use App\Models\AppointmentScheduleOverride;
use App\Models\CheckInSchedule;
use App\Models\CheckInScheduleOverride;
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

it('renders each section of a location with the shared summary', function (string $route, string $component, string $prop): void {
    $location = Location::factory()->create();

    $this->actingAs(locationsAdmin())
        ->get(route($route, ['uuid' => $location->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component($component)
            ->where('location.uuid', $location->uuid)
            ->has($prop)
            ->missing('location.id'));
})->with([
    'overview' => ['dashboard.locations.show', 'Dashboard/Locations/Overview', 'overview'],
    'details' => ['dashboard.locations.details', 'Dashboard/Locations/Details', 'details'],
    'users' => ['dashboard.locations.users', 'Dashboard/Locations/Users', 'users'],
    'check-in hours' => ['dashboard.locations.checkInHours', 'Dashboard/Locations/CheckInHours', 'hours'],
    'check-in options' => ['dashboard.locations.checkInOptions', 'Dashboard/Locations/CheckInOptions', 'options'],
    'appointment hours' => ['dashboard.locations.appointmentHours', 'Dashboard/Locations/AppointmentHours', 'hours'],
    'appointment options' => ['dashboard.locations.appointmentOptions', 'Dashboard/Locations/AppointmentOptions', 'options'],
    'closures' => ['dashboard.locations.closures', 'Dashboard/Locations/Closures', 'closures'],
    'history' => ['dashboard.locations.history', 'Dashboard/Locations/History', 'history'],
]);

it('sends the address and map point on the details section', function (): void {
    $location = Location::factory()->create([
        'address_id' => Address::factory()->create(['city' => 'Miami', 'latitude' => 25.7617, 'longitude' => -80.1918]),
        'max_distance_allowed' => 75,
    ]);

    $this->actingAs(locationsAdmin())
        ->get(route('dashboard.locations.details', ['uuid' => $location->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('details.address.city', 'Miami')
            ->where('details.map.latitude', 25.7617)
            ->where('details.map.longitude', -80.1918)
            ->where('details.map.maxDistanceAllowed', 75));
});

it('sends attached staff apart from staff who see every location', function (): void {
    $location = Location::factory()->create();
    $shipping = User::factory()->withRole(Role::Shipping)->worksAt($location)->create();
    $admin = locationsAdmin();

    $this->actingAs($admin)
        ->get(route('dashboard.locations.users', ['uuid' => $location->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('users.attached', 1)
            ->where('users.attached.0.uuid', $shipping->uuid)
            ->where('users.attached.0.roles', [Role::Shipping->value])
            ->where('users.allLocations.0.uuid', $admin->uuid)
            ->missing('attachableUsers'));
});

it('sends only unattached location-scoped staff as candidates on a partial reload', function (): void {
    $location = Location::factory()->create();
    User::factory()->withRole(Role::Shipping)->worksAt($location)->create();
    $candidate = User::factory()->withRole(Role::ShippingManager)->create();
    User::factory()->withRole(Role::Sales)->create();
    User::factory()->withRole(Role::Driver)->create();

    $this->actingAs(locationsAdmin())
        ->get(route('dashboard.locations.users', ['uuid' => $location->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->reloadOnly('attachableUsers', fn (AssertableInertia $reload): AssertableInertia => $reload
                ->has('attachableUsers', 1)
                ->where('attachableUsers.0.uuid', $candidate->uuid)));
});

it('lists the week Monday first, with days without hours as closed', function (): void {
    $location = Location::factory()->checkinClosedAllWeek()->create();
    CheckInSchedule::factory()->for($location)->create(['day_of_week' => 1, 'open_time' => '08:00:00', 'close_time' => '17:30:00']);

    $this->actingAs(locationsAdmin())
        ->get(route('dashboard.locations.checkInHours', ['uuid' => $location->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('hours', 7)
            ->where('hours.0', ['dayOfWeek' => 1, 'openTime' => '08:00', 'closeTime' => '17:30'])
            ->where('hours.6', ['dayOfWeek' => 0, 'openTime' => null, 'closeTime' => null]));
});

it('merges a closure entered the same way for both features into one row', function (): void {
    $this->travelTo('2026-12-01 12:00:00');
    $location = Location::factory()->create(['timezone' => 'America/Chicago']);
    $holiday = ['override_date' => '2026-12-25', 'is_closed' => true, 'open_time' => null, 'close_time' => null, 'reason' => 'Christmas'];
    CheckInScheduleOverride::factory()->for($location)->create($holiday);
    AppointmentScheduleOverride::factory()->for($location)->create($holiday);
    CheckInScheduleOverride::factory()->for($location)->create(['override_date' => '2026-12-24', 'is_closed' => false, 'open_time' => '08:00:00', 'close_time' => '12:00:00', 'reason' => 'Christmas Eve']);
    AppointmentScheduleOverride::factory()->for($location)->create(['override_date' => '2026-12-24', 'is_closed' => true, 'open_time' => null, 'close_time' => null, 'reason' => 'Christmas Eve']);
    CheckInScheduleOverride::factory()->for($location)->create(['override_date' => '2026-11-26', 'is_closed' => true, 'reason' => 'Thanksgiving']);

    $this->actingAs(locationsAdmin())
        ->get(route('dashboard.locations.closures', ['uuid' => $location->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('closures.upcoming', 3)
            ->where('closures.upcoming.0.date', '2026-12-24')
            ->where('closures.upcoming.0.appliesTo', ['appointments'])
            ->where('closures.upcoming.1.appliesTo', ['checkIns'])
            ->where('closures.upcoming.1.openTime', '08:00')
            ->where('closures.upcoming.2', [
                'date' => '2026-12-25',
                'isClosed' => true,
                'openTime' => null,
                'closeTime' => null,
                'reason' => 'Christmas',
                'appliesTo' => ['checkIns', 'appointments'],
            ])
            ->has('closures.past', 1)
            ->where('closures.past.0.reason', 'Thanksgiving'));
});

it("reports whether check-ins are open now in the location's timezone", function (): void {
    // 15:00 UTC is 07:00 in Los Angeles: before an 08:00 opening there.
    $this->travelTo('2026-12-02 15:00:00');
    $location = Location::factory()->checkinClosedAllWeek()->create(['timezone' => 'America/Los_Angeles']);
    CheckInSchedule::factory()->for($location)->create(['day_of_week' => 3, 'open_time' => '08:00:00', 'close_time' => '17:00:00']);

    $this->actingAs(locationsAdmin())
        ->get(route('dashboard.locations.show', ['uuid' => $location->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('overview.checkIns.isOpen', false)
            ->where('overview.checkIns.openTime', '08:00'));

    $this->travelTo('2026-12-02 17:00:00');

    $this->get(route('dashboard.locations.show', ['uuid' => $location->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('overview.checkIns.isOpen', true));
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

    $this->get(route('dashboard.locations.users', ['uuid' => $other->uuid]))
        ->assertNotFound();
});
