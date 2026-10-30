<?php

declare(strict_types=1);

use App\Enums\LoadDirection;
use App\Enums\RecordHistoryEvent;
use App\Enums\Role;
use App\Enums\UserHistoryEvent;
use App\Models\Appointment;
use App\Models\Dock;
use App\Models\LoadType;
use App\Models\Location;
use App\Models\User;
use App\Models\UserHistory;
use Inertia\Testing\AssertableInertia;

/*
 * Adding, editing and removing the load types drivers book at a location.
 */

function loadTypesManager(Location $location): User
{
    return User::factory()->withRole(Role::ShippingManager)->worksAt($location)->create();
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function loadTypePayloadFor(array $overrides = []): array
{
    return [
        'name' => 'Live unload',
        'direction' => 'inbound',
        'duration_minutes' => 90,
        'is_active' => true,
        ...$overrides,
    ];
}

it('adds a load type and logs it on both trails', function (): void {
    $location = Location::factory()->create();
    $manager = loadTypesManager($location);

    $this->actingAs($manager)
        ->post(route('dashboard.locations.loadTypes.store', ['uuid' => $location->uuid]), loadTypePayloadFor())
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', 'Live unload added.');

    $loadType = LoadType::query()->sole();
    $history = $location->history()->sole();

    expect($loadType->location_id)->toBe($location->id)
        ->and($loadType->direction)->toBe(LoadDirection::Inbound)
        ->and($loadType->duration_minutes)->toBe(90)
        ->and($history->event)->toBe(RecordHistoryEvent::LoadTypeAdded)
        ->and($history->user_id)->toBe($manager->id)
        ->and($history->context)->toBe(['loadType' => 'Live unload'])
        ->and(UserHistory::query()->sole()->event)->toBe(UserHistoryEvent::LocationLoadTypesEdited);
});

it('rejects a load type name the location already uses', function (): void {
    $location = Location::factory()->create();
    LoadType::factory()->for($location)->create(['name' => 'Live unload']);

    $this->actingAs(loadTypesManager($location))
        ->post(route('dashboard.locations.loadTypes.store', ['uuid' => $location->uuid]), loadTypePayloadFor())
        ->assertSessionHasErrors(['name' => 'This location already has a load type with that name.']);
});

it('rejects a duration that is not a whole number of 15-minute steps up to 8 hours', function (int $minutes, string $message): void {
    $location = Location::factory()->create();

    $this->actingAs(loadTypesManager($location))
        ->post(route('dashboard.locations.loadTypes.store', ['uuid' => $location->uuid]), loadTypePayloadFor(['duration_minutes' => $minutes]))
        ->assertSessionHasErrors(['duration_minutes' => $message]);

    expect(LoadType::query()->count())->toBe(0);
})->with([
    'under 15 minutes' => [10, 'A load takes at least 15 minutes.'],
    'over 8 hours' => [495, 'A load can take up to 8 hours.'],
    'off the 15-minute step' => [50, 'Use steps of 15 minutes.'],
]);

it('forbids staff without manage load types', function (): void {
    $location = Location::factory()->create();
    $shipping = User::factory()->withRole(Role::Shipping)->worksAt($location)->create();

    $this->actingAs($shipping)
        ->post(route('dashboard.locations.loadTypes.store', ['uuid' => $location->uuid]), loadTypePayloadFor())
        ->assertForbidden();
});

it('hides a location the manager is not attached to', function (): void {
    $location = Location::factory()->create();
    $manager = loadTypesManager(Location::factory()->create());

    $this->actingAs($manager)
        ->post(route('dashboard.locations.loadTypes.store', ['uuid' => $location->uuid]), loadTypePayloadFor())
        ->assertNotFound();
});

it('hides a load type on another location', function (): void {
    $location = Location::factory()->create();
    $elsewhere = LoadType::factory()->create();

    $this->actingAs(loadTypesManager($location))
        ->patch(route('dashboard.locations.loadTypes.update', ['uuid' => $location->uuid, 'loadTypeUuid' => $elsewhere->uuid]), loadTypePayloadFor())
        ->assertNotFound();
});

it('logs only the fields an edit changed, leaving booked appointments their length', function (): void {
    $location = Location::factory()->create();
    $loadType = LoadType::factory()->for($location)->lasting(60)->create(['name' => 'Live unload']);
    $booked = Appointment::factory()->scheduledFor(now()->addDay())->ofLoadType($loadType)->create();
    $endsAt = $booked->ends_at;

    $this->actingAs(loadTypesManager($location))
        ->patch(route('dashboard.locations.loadTypes.update', ['uuid' => $location->uuid, 'loadTypeUuid' => $loadType->uuid]), loadTypePayloadFor(['duration_minutes' => 120]))
        ->assertSessionHasNoErrors();

    expect($loadType->refresh()->duration_minutes)->toBe(120)
        ->and($booked->refresh()->ends_at->equalTo($endsAt))->toBeTrue()
        ->and($location->history()->sole()->context)->toBe([
            'loadType' => 'Live unload',
            'duration_minutes' => ['from' => 60, 'to' => 120],
        ]);
});

it('writes no history when an edit changes nothing', function (): void {
    $location = Location::factory()->create();
    $loadType = LoadType::factory()->for($location)->lasting(90)->create(['name' => 'Live unload']);

    $this->actingAs(loadTypesManager($location))
        ->patch(route('dashboard.locations.loadTypes.update', ['uuid' => $location->uuid, 'loadTypeUuid' => $loadType->uuid]), loadTypePayloadFor())
        ->assertSessionHasNoErrors();

    expect($location->history()->count())->toBe(0);
});

it('removes a load type that was never booked', function (): void {
    $location = Location::factory()->create();
    $loadType = LoadType::factory()->for($location)->create();

    $this->actingAs(loadTypesManager($location))
        ->delete(route('dashboard.locations.loadTypes.destroy', ['uuid' => $location->uuid, 'loadTypeUuid' => $loadType->uuid]))
        ->assertSessionHasNoErrors();

    expect(LoadType::query()->count())->toBe(0)
        ->and($location->history()->sole()->event)->toBe(RecordHistoryEvent::LoadTypeRemoved);
});

it('keeps a load type that was ever booked, archived bookings included', function (): void {
    $location = Location::factory()->create();
    $loadType = LoadType::factory()->for($location)->create();
    Appointment::factory()->scheduledFor(now()->subWeek())->ofLoadType($loadType)->create()->delete();

    $this->actingAs(loadTypesManager($location))
        ->delete(route('dashboard.locations.loadTypes.destroy', ['uuid' => $location->uuid, 'loadTypeUuid' => $loadType->uuid]))
        ->assertSessionHasErrors(['loadType' => 'Appointments have been booked with this load type, so it cannot be removed. Turn it off instead.']);

    expect(LoadType::query()->count())->toBe(1);
});

it('shows the load types page with every load type, turned off ones included', function (): void {
    $location = Location::factory()->create();
    LoadType::factory()->for($location)->create(['name' => 'Live unload', 'sort_order' => 0]);
    LoadType::factory()->for($location)->outbound()->inactive()->create(['name' => 'Live load', 'sort_order' => 1]);

    $this->actingAs(loadTypesManager($location))
        ->get(route('dashboard.locations.loadTypes', ['uuid' => $location->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Dashboard/Locations/LoadTypes')
            ->where('loadTypes.0.name', 'Live unload')
            ->where('loadTypes.1.name', 'Live load')
            ->where('loadTypes.1.direction', 'outbound')
            ->where('loadTypes.1.isActive', false));
});

it('tells the load types page how many trucks the docks hold at once', function (int $dockCount, ?int $expected): void {
    $location = Location::factory()->create();
    LoadType::factory()->for($location)->create();
    Dock::factory()->for($location)->count($dockCount)->create();

    $this->actingAs(loadTypesManager($location))
        ->get(route('dashboard.locations.loadTypes', ['uuid' => $location->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Dashboard/Locations/LoadTypes')
            ->where('dockCapacity', $expected));
})->with([
    'no docks, so durations block nothing' => [0, null],
    'two docks' => [2, 2],
]);
