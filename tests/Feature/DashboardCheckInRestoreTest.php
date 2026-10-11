<?php

declare(strict_types=1);

use App\Enums\CheckInStatus;
use App\Enums\RecordHistoryEvent;
use App\Enums\Role;
use App\Enums\UserHistoryEvent;
use App\Events\CheckInUpdated;
use App\Models\CheckIn;
use App\Models\Location;
use App\Models\RecordHistory;
use App\Models\User;
use App\Models\UserHistory;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia;

/*
 * Restoring an archived check-in from the dashboard: who may do it, the two
 * trails it writes, the broadcast, and the states it refuses.
 */

function restoreHistoryRows(CheckIn $checkIn): int
{
    return RecordHistory::query()
        ->where('recordable_id', $checkIn->id)
        ->where('event', RecordHistoryEvent::Restored)
        ->count();
}

it('restores an archived check-in and logs it on the timeline and the staff trail', function (Role $role): void {
    Event::fake([CheckInUpdated::class]);
    $location = Location::factory()->create();
    $checkIn = CheckIn::factory()->atLocation($location)->cancelled()->archived()->create();
    $staff = User::factory()->withRole($role)->worksAt($location)->create();

    $this->actingAs($staff)
        ->from(route('dashboard.checkIns.show', ['uuid' => $checkIn->uuid]))
        ->post(route('dashboard.checkIns.restore', ['uuid' => $checkIn->uuid]))
        ->assertRedirect(route('dashboard.checkIns.show', ['uuid' => $checkIn->uuid]));

    $checkIn->refresh();
    expect($checkIn->trashed())->toBeFalse()
        ->and($checkIn->status)->toBe(CheckInStatus::Cancelled);

    $restored = RecordHistory::query()->where('event', RecordHistoryEvent::Restored)->sole();
    expect($restored->recordable_id)->toBe($checkIn->id)
        ->and($restored->user_id)->toBe($staff->id)
        ->and($restored->context)->toBeNull();

    $trail = UserHistory::query()->sole();
    expect($trail->user_id)->toBe($staff->id)
        ->and($trail->event)->toBe(UserHistoryEvent::CheckInRestored)
        ->and($trail->changes)->toBe(['check_in' => $checkIn->uuid]);

    Event::assertDispatched(CheckInUpdated::class, fn (CheckInUpdated $event): bool => $event->checkIn->is($checkIn));
})->with([
    'admin' => [Role::Admin],
    'shipping manager' => [Role::ShippingManager],
    'shipping' => [Role::Shipping],
]);

it('forbids other roles from restoring a check-in', function (Role $role): void {
    $location = Location::factory()->create();
    $checkIn = CheckIn::factory()->atLocation($location)->completed()->archived()->create();

    $this->actingAs(User::factory()->withRole($role)->worksAt($location)->create())
        ->post(route('dashboard.checkIns.restore', ['uuid' => $checkIn->uuid]))
        ->assertForbidden();

    expect(CheckIn::withTrashed()->find($checkIn->id)->trashed())->toBeTrue()
        ->and(restoreHistoryRows($checkIn))->toBe(0)
        ->and(UserHistory::query()->count())->toBe(0);
})->with([
    'sales' => [Role::Sales],
    'driver' => [Role::Driver],
]);

it('sends a guest to sign in before restoring a check-in', function (): void {
    $checkIn = CheckIn::factory()->completed()->archived()->create();

    $this->post(route('dashboard.checkIns.restore', ['uuid' => $checkIn->uuid]))
        ->assertRedirect(route('login'));

    expect(CheckIn::withTrashed()->find($checkIn->id)->trashed())->toBeTrue();
});

it('returns not found for a check-in at a location the user does not work', function (): void {
    $checkIn = CheckIn::factory()->completed()->archived()->create();
    $manager = User::factory()->withRole(Role::ShippingManager)->worksAt(Location::factory()->create())->create();

    $this->actingAs($manager)
        ->post(route('dashboard.checkIns.restore', ['uuid' => $checkIn->uuid]))
        ->assertNotFound();

    expect(CheckIn::withTrashed()->find($checkIn->id)->trashed())->toBeTrue()
        ->and(UserHistory::query()->count())->toBe(0);
});

it('returns not found for a check-in that is not archived', function (): void {
    $checkIn = CheckIn::factory()->completed()->create();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->post(route('dashboard.checkIns.restore', ['uuid' => $checkIn->uuid]))
        ->assertNotFound();

    expect(restoreHistoryRows($checkIn))->toBe(0)
        ->and(UserHistory::query()->count())->toBe(0);
});

it('shows the restore on the timeline', function (): void {
    $staff = User::factory()->withRole(Role::Admin)->create(['name' => 'Dana Lee']);
    $checkIn = CheckIn::factory()->completed()->archived()->create();

    $this->actingAs($staff)->post(route('dashboard.checkIns.restore', ['uuid' => $checkIn->uuid]));

    $this->actingAs($staff)
        ->get(route('dashboard.checkIns.show', ['uuid' => $checkIn->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('checkIn.archivedAt', null)
            ->where('checkIn.history', fn ($history): bool => collect($history)
                ->where('actor', 'Dana Lee')
                ->contains('description', 'Restored from archive')));
});
