<?php

declare(strict_types=1);

use App\Enums\RecordHistoryEvent;
use App\Enums\Role;
use App\Enums\UserHistoryEvent;
use App\Models\Appointment;
use App\Models\Building;
use App\Models\CheckIn;
use App\Models\Dock;
use App\Models\LoadType;
use App\Models\Location;
use App\Models\User;
use App\Models\UserHistory;
use Inertia\Testing\AssertableInertia;

/*
 * Adding, editing and removing a location's docks and buildings from its docks page.
 */

function docksManager(Location $location): User
{
    return User::factory()->withRole(Role::ShippingManager)->worksAt($location)->create();
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function dockPayload(array $overrides = []): array
{
    return [
        'name' => 'Door 12',
        'building' => null,
        'is_active' => true,
        'accepts_appointments' => true,
        ...$overrides,
    ];
}

it('adds a dock in a building and logs it on both trails', function (): void {
    $location = Location::factory()->create();
    $building = Building::factory()->for($location)->create(['name' => 'Building B']);
    $manager = docksManager($location);

    $this->actingAs($manager)
        ->post(route('dashboard.locations.docks.store', ['uuid' => $location->uuid]), dockPayload(['building' => $building->uuid]))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', 'Door 12 added.');

    $dock = Dock::query()->sole();
    $history = $location->history()->sole();

    expect($dock->location_id)->toBe($location->id)
        ->and($dock->building_id)->toBe($building->id)
        ->and($history->event)->toBe(RecordHistoryEvent::DockAdded)
        ->and($history->user_id)->toBe($manager->id)
        ->and($history->context)->toBe(['dock' => 'Building B / Door 12'])
        ->and(UserHistory::query()->sole()->event)->toBe(UserHistoryEvent::LocationDocksEdited);
});

it('rejects a dock name the location already uses', function (): void {
    $location = Location::factory()->create();
    Dock::factory()->for($location)->create(['name' => 'Door 12']);

    $this->actingAs(docksManager($location))
        ->post(route('dashboard.locations.docks.store', ['uuid' => $location->uuid]), dockPayload())
        ->assertSessionHasErrors(['name' => 'This location already has a dock with that name.']);
});

it('rejects a building from another location', function (): void {
    $location = Location::factory()->create();
    $otherBuilding = Building::factory()->create();

    $this->actingAs(docksManager($location))
        ->post(route('dashboard.locations.docks.store', ['uuid' => $location->uuid]), dockPayload(['building' => $otherBuilding->uuid]))
        ->assertSessionHasErrors(['building' => 'Choose a building at this location.']);

    expect(Dock::query()->count())->toBe(0);
});

it('forbids staff without manage docks', function (): void {
    $location = Location::factory()->create();
    $shipping = User::factory()->withRole(Role::Shipping)->worksAt($location)->create();

    $this->actingAs($shipping)
        ->post(route('dashboard.locations.docks.store', ['uuid' => $location->uuid]), dockPayload())
        ->assertForbidden();
});

it('hides a location the manager is not attached to', function (): void {
    $location = Location::factory()->create();
    $manager = docksManager(Location::factory()->create());

    $this->actingAs($manager)
        ->post(route('dashboard.locations.docks.store', ['uuid' => $location->uuid]), dockPayload())
        ->assertNotFound();
});

it('hides a dock on another location', function (): void {
    $location = Location::factory()->create();
    $otherDock = Dock::factory()->create();

    $this->actingAs(docksManager($location))
        ->patch(route('dashboard.locations.docks.update', ['uuid' => $location->uuid, 'dockUuid' => $otherDock->uuid]), dockPayload())
        ->assertNotFound();
});

it('logs only the fields an edit changed', function (): void {
    $location = Location::factory()->create();
    $dock = Dock::factory()->for($location)->create(['name' => 'Door 1']);

    $this->actingAs(docksManager($location))
        ->patch(route('dashboard.locations.docks.update', ['uuid' => $location->uuid, 'dockUuid' => $dock->uuid]), dockPayload([
            'name' => 'Door 1',
            'accepts_appointments' => false,
        ]))
        ->assertSessionHasNoErrors();

    expect($dock->refresh()->accepts_appointments)->toBeFalse()
        ->and($location->history()->sole()->context)->toBe([
            'dock' => 'Door 1',
            'accepts_appointments' => ['from' => true, 'to' => false],
        ]);
});

it('writes no history when an edit changes nothing', function (): void {
    $location = Location::factory()->create();
    $dock = Dock::factory()->for($location)->create(['name' => 'Door 1']);

    $this->actingAs(docksManager($location))
        ->patch(route('dashboard.locations.docks.update', ['uuid' => $location->uuid, 'dockUuid' => $dock->uuid]), dockPayload(['name' => 'Door 1']))
        ->assertSessionHasNoErrors();

    expect($location->history()->count())->toBe(0);
});

it('keeps a dock open while upcoming appointments are placed on it', function (string $field): void {
    $location = Location::factory()->create();
    $dock = Dock::factory()->for($location)->create(['name' => 'Door 1']);
    Appointment::factory()->for($location)->create([
        'dock_id' => $dock->id,
        'scheduled_for' => now()->addDay(),
        'reference_number' => 'APT12345',
    ]);

    $this->actingAs(docksManager($location))
        ->patch(route('dashboard.locations.docks.update', ['uuid' => $location->uuid, 'dockUuid' => $dock->uuid]), dockPayload([
            'name' => 'Door 1',
            $field => false,
        ]))
        ->assertSessionHasErrors(['is_active' => 'Move the upcoming appointments on this dock first: APT12345.']);

    expect($dock->refresh()->is_active)->toBeTrue()
        ->and($dock->accepts_appointments)->toBeTrue();
})->with(['is_active', 'accepts_appointments']);

it('removes a dock that never held an appointment', function (): void {
    $location = Location::factory()->create();
    $dock = Dock::factory()->for($location)->create(['name' => 'Door 1']);

    $this->actingAs(docksManager($location))
        ->delete(route('dashboard.locations.docks.destroy', ['uuid' => $location->uuid, 'dockUuid' => $dock->uuid]))
        ->assertSessionHasNoErrors();

    expect(Dock::query()->count())->toBe(0)
        ->and($location->history()->sole()->event)->toBe(RecordHistoryEvent::DockRemoved);
});

it('keeps a dock that ever held an appointment, archived ones included', function (): void {
    $location = Location::factory()->create();
    $dock = Dock::factory()->for($location)->create();
    Appointment::factory()->for($location)->create(['dock_id' => $dock->id, 'scheduled_for' => now()->subWeek()])->delete();

    $this->actingAs(docksManager($location))
        ->delete(route('dashboard.locations.docks.destroy', ['uuid' => $location->uuid, 'dockUuid' => $dock->uuid]))
        ->assertSessionHasErrors(['dock' => 'This dock has appointments or check-ins on record, so it cannot be removed. Turn it off instead.']);

    expect(Dock::query()->count())->toBe(1);
});

it('keeps a dock that ever held a check-in', function (): void {
    $location = Location::factory()->create();
    $dock = Dock::factory()->for($location)->create();
    CheckIn::factory()->atLocation($location)->completed()->create(['dock_id' => $dock->id]);

    $this->actingAs(docksManager($location))
        ->delete(route('dashboard.locations.docks.destroy', ['uuid' => $location->uuid, 'dockUuid' => $dock->uuid]))
        ->assertSessionHasErrors(['dock' => 'This dock has appointments or check-ins on record, so it cannot be removed. Turn it off instead.']);

    expect(Dock::query()->count())->toBe(1);
});

it('adds and renames a building', function (): void {
    $location = Location::factory()->create();
    $manager = docksManager($location);

    $this->actingAs($manager)
        ->post(route('dashboard.locations.buildings.store', ['uuid' => $location->uuid]), ['name' => 'Building A'])
        ->assertSessionHasNoErrors();

    $building = Building::query()->sole();

    $this->actingAs($manager)
        ->patch(route('dashboard.locations.buildings.update', ['uuid' => $location->uuid, 'buildingUuid' => $building->uuid]), ['name' => 'North'])
        ->assertSessionHasNoErrors();

    expect($building->refresh()->name)->toBe('North')
        ->and($location->history()->pluck('event')->all())->toBe([RecordHistoryEvent::BuildingEdited, RecordHistoryEvent::BuildingAdded]);
});

it("ungroups a removed building's docks instead of deleting them", function (): void {
    $location = Location::factory()->create();
    $building = Building::factory()->for($location)->create();
    $dock = Dock::factory()->for($location)->for($building)->create();

    $this->actingAs(docksManager($location))
        ->delete(route('dashboard.locations.buildings.destroy', ['uuid' => $location->uuid, 'buildingUuid' => $building->uuid]))
        ->assertSessionHasNoErrors();

    expect(Building::query()->count())->toBe(0)
        ->and($dock->refresh()->building_id)->toBeNull();
});

it('shows the docks page with the slot capacity', function (): void {
    $location = Location::factory()->create();
    Dock::factory()->for($location)->create(['name' => 'Door 10']);
    Dock::factory()->for($location)->create(['name' => 'Door 2']);
    Dock::factory()->for($location)->inactive()->create(['name' => 'Door 3']);

    $this->actingAs(docksManager($location))
        ->get(route('dashboard.locations.docks', ['uuid' => $location->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Dashboard/Locations/Docks')
            ->where('docks.capacity', 2)
            ->where('docks.docks.0.name', 'Door 2')
            ->where('docks.docks.1.name', 'Door 3')
            ->where('docks.docks.2.name', 'Door 10'));
});

it('tells the docks page whether drivers pick a load type yet', function (bool $active): void {
    $location = Location::factory()->create();
    Dock::factory()->for($location)->create();
    LoadType::factory()->for($location)->state(['is_active' => $active])->create();

    $this->actingAs(docksManager($location))
        ->get(route('dashboard.locations.docks', ['uuid' => $location->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Dashboard/Locations/Docks')
            ->where('hasActiveLoadTypes', $active));
})->with([
    'only a turned-off load type' => false,
    'an active load type' => true,
]);
