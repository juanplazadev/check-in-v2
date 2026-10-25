<?php

declare(strict_types=1);

use App\Enums\RecordHistoryEvent;
use App\Enums\Role;
use App\Enums\UserHistoryEvent;
use App\Models\CheckIn;
use App\Models\Location;
use App\Models\User;
use App\Models\UserHistory;
use Illuminate\Support\Str;

/*
 * Attaching staff to a location and detaching them, from the location's Users section.
 */

it('attaches location-scoped staff and logs each on the admin trail', function (): void {
    $admin = User::factory()->withRole(Role::Admin)->create();
    $location = Location::factory()->create(['name' => 'Alpha Dock']);
    $shipping = User::factory()->withRole(Role::Shipping)->create(['name' => 'Sam']);
    $manager = User::factory()->withRole(Role::ShippingManager)->create();

    $this->actingAs($admin)
        ->from(route('dashboard.locations.users', ['uuid' => $location->uuid]))
        ->post(route('dashboard.locations.users.store', ['uuid' => $location->uuid]), [
            'users' => [$shipping->uuid, $manager->uuid],
        ])
        ->assertRedirect(route('dashboard.locations.users', ['uuid' => $location->uuid]))
        ->assertInertiaFlash('toast.message', '2 users were added to Alpha Dock.');

    expect($location->users()->pluck('users.id')->sort()->values()->all())->toBe([$shipping->id, $manager->id])
        ->and(UserHistory::query()->where('user_id', $admin->id)->pluck('event')->all())
        ->toBe([UserHistoryEvent::LocationUserAttached, UserHistoryEvent::LocationUserAttached])
        ->and($location->history()->pluck('event')->all())
        ->toBe([RecordHistoryEvent::LocationUserAttached, RecordHistoryEvent::LocationUserAttached]);
});

it('leaves an already attached user alone', function (): void {
    $admin = User::factory()->withRole(Role::Admin)->create();
    $location = Location::factory()->create();
    $shipping = User::factory()->withRole(Role::Shipping)->worksAt($location)->create();

    $this->actingAs($admin)
        ->post(route('dashboard.locations.users.store', ['uuid' => $location->uuid]), ['users' => [$shipping->uuid]])
        ->assertSessionHasNoErrors();

    expect($location->users()->count())->toBe(1)
        ->and(UserHistory::query()->count())->toBe(0);
});

it('refuses users who are not location-scoped staff', function (Closure $uuid): void {
    $location = Location::factory()->create();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->post(route('dashboard.locations.users.store', ['uuid' => $location->uuid]), ['users' => [$uuid()]])
        ->assertSessionHasErrors(['users' => 'Only staff limited to their own locations can be added.']);

    expect($location->users()->count())->toBe(0);
})->with([
    'unknown' => [fn (): string => (string) Str::uuid()],
    'sees every location' => [fn (): string => User::factory()->withRole(Role::Sales)->create()->uuid],
    'driver' => [fn (): string => User::factory()->withRole(Role::Driver)->create()->uuid],
]);

it('requires at least one user', function (): void {
    $location = Location::factory()->create();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->post(route('dashboard.locations.users.store', ['uuid' => $location->uuid]), ['users' => []])
        ->assertSessionHasErrors('users');
});

it('detaches a user and logs it, so they stop seeing the location', function (): void {
    $admin = User::factory()->withRole(Role::Admin)->create();
    $location = Location::factory()->create();
    $shipping = User::factory()->withRole(Role::Shipping)->worksAt($location)->create();
    $checkIn = CheckIn::factory()->for($location)->create();

    $this->actingAs($shipping)
        ->get(route('dashboard.checkIns.show', ['uuid' => $checkIn->uuid]))
        ->assertSuccessful();

    $this->actingAs($admin)
        ->delete(route('dashboard.locations.users.destroy', ['uuid' => $location->uuid, 'userUuid' => $shipping->uuid]))
        ->assertRedirect();

    $trail = UserHistory::query()->sole();
    expect($location->users()->count())->toBe(0)
        ->and($trail->event)->toBe(UserHistoryEvent::LocationUserDetached)
        ->and($trail->changes)->toBe(['location' => $location->uuid, 'user' => $shipping->uuid])
        ->and($location->history()->sole()->context)->toBe(['user' => $shipping->uuid, 'name' => $shipping->name]);

    $this->actingAs($shipping->fresh())
        ->get(route('dashboard.checkIns.show', ['uuid' => $checkIn->uuid]))
        ->assertNotFound();
});

it('returns not found when detaching a user who is not attached', function (): void {
    $location = Location::factory()->create();
    $shipping = User::factory()->withRole(Role::Shipping)->create();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->delete(route('dashboard.locations.users.destroy', ['uuid' => $location->uuid, 'userUuid' => $shipping->uuid]))
        ->assertNotFound();
});

it('forbids staff without manage location users', function (): void {
    $location = Location::factory()->create();
    $manager = User::factory()->withRole(Role::ShippingManager)->worksAt($location)->create();
    $shipping = User::factory()->withRole(Role::Shipping)->create();

    $this->actingAs($manager)
        ->post(route('dashboard.locations.users.store', ['uuid' => $location->uuid]), ['users' => [$shipping->uuid]])
        ->assertForbidden();

    expect($location->users()->count())->toBe(1);
});
