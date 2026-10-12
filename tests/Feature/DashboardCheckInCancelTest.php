<?php

declare(strict_types=1);

use App\Enums\CheckInStatus;
use App\Enums\RecordHistoryEvent;
use App\Enums\Role;
use App\Enums\UserHistoryEvent;
use App\Models\CheckIn;
use App\Models\Location;
use App\Models\RecordHistory;
use App\Models\User;
use App\Models\UserHistory;
use Inertia\Testing\AssertableInertia;

/*
 * Cancelling a pending check-in from the dashboard: who may do it, the two
 * trails it writes, the reason it keeps, and the states it refuses.
 */

function cancelStatusChangedRows(CheckIn $checkIn): int
{
    return RecordHistory::query()
        ->where('recordable_id', $checkIn->id)
        ->where('event', RecordHistoryEvent::StatusChanged)
        ->count();
}

it('cancels a pending check-in and logs it on the timeline and the staff trail', function (Role $role): void {
    $location = Location::factory()->create();
    $checkIn = CheckIn::factory()->atLocation($location)->pending()->create();
    $staff = User::factory()->withRole($role)->worksAt($location)->create();
    $before = cancelStatusChangedRows($checkIn);

    $this->actingAs($staff)
        ->from(route('dashboard.checkIns.show', ['uuid' => $checkIn->uuid]))
        ->post(route('dashboard.checkIns.cancel', ['uuid' => $checkIn->uuid]), ['reason' => 'Load canceled by shipper'])
        ->assertRedirect(route('dashboard.checkIns.show', ['uuid' => $checkIn->uuid]));

    $checkIn->refresh();
    expect($checkIn->status)->toBe(CheckInStatus::Cancelled)
        ->and($checkIn->cancelled_at)->not->toBeNull()
        ->and($checkIn->cancelled_by)->toBe($staff->id)
        ->and($checkIn->cancelled_reason)->toBe('Load canceled by shipper')
        ->and(cancelStatusChangedRows($checkIn))->toBe($before + 1);

    $entry = RecordHistory::query()->where('event', RecordHistoryEvent::StatusChanged)->latest('id')->first();
    expect($entry->recordable_id)->toBe($checkIn->id)
        ->and($entry->subject)->toBe('status')
        ->and($entry->user_id)->toBe($staff->id)
        ->and($entry->context)->toBe(['from' => 'pending', 'to' => 'cancelled']);

    $trail = UserHistory::query()->sole();
    expect($trail->user_id)->toBe($staff->id)
        ->and($trail->event)->toBe(UserHistoryEvent::CheckInCancelled)
        ->and($trail->changes)->toBe(['check_in' => $checkIn->uuid, 'reason' => 'Load canceled by shipper']);
})->with([
    'admin' => [Role::Admin],
    'shipping manager' => [Role::ShippingManager],
    'shipping' => [Role::Shipping],
]);

it('forbids other roles from cancelling a check-in', function (Role $role): void {
    $location = Location::factory()->create();
    $checkIn = CheckIn::factory()->atLocation($location)->pending()->create();

    $this->actingAs(User::factory()->withRole($role)->worksAt($location)->create())
        ->post(route('dashboard.checkIns.cancel', ['uuid' => $checkIn->uuid]), ['reason' => 'No longer needed'])
        ->assertForbidden();

    expect($checkIn->refresh()->status)->toBe(CheckInStatus::Pending)
        ->and(UserHistory::query()->count())->toBe(0);
})->with([
    'sales' => [Role::Sales],
    'driver' => [Role::Driver],
]);

it('sends a guest to sign in before cancelling a check-in', function (): void {
    $checkIn = CheckIn::factory()->pending()->create();

    $this->post(route('dashboard.checkIns.cancel', ['uuid' => $checkIn->uuid]), ['reason' => 'No longer needed'])
        ->assertRedirect(route('login'));

    expect($checkIn->refresh()->status)->toBe(CheckInStatus::Pending);
});

it('returns not found for a check-in at a location the user does not work', function (): void {
    $checkIn = CheckIn::factory()->pending()->create();
    $shipping = User::factory()->withRole(Role::Shipping)->worksAt(Location::factory()->create())->create();

    $this->actingAs($shipping)
        ->post(route('dashboard.checkIns.cancel', ['uuid' => $checkIn->uuid]), ['reason' => 'No longer needed'])
        ->assertNotFound();

    expect($checkIn->refresh()->status)->toBe(CheckInStatus::Pending)
        ->and(UserHistory::query()->count())->toBe(0);
});

it('returns not found for an archived check-in', function (): void {
    $checkIn = CheckIn::factory()->pending()->create();
    $checkIn->delete();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->post(route('dashboard.checkIns.cancel', ['uuid' => $checkIn->uuid]), ['reason' => 'No longer needed'])
        ->assertNotFound();

    expect(CheckIn::withTrashed()->find($checkIn->id)->status)->toBe(CheckInStatus::Pending);
});

it('refuses a check-in that is no longer pending', function (string $state): void {
    $checkIn = CheckIn::factory()->{$state}()->create();
    $before = cancelStatusChangedRows($checkIn);

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->post(route('dashboard.checkIns.cancel', ['uuid' => $checkIn->uuid]), ['reason' => 'No longer needed'])
        ->assertSessionHasErrors(['status' => 'This check-in is no longer pending.']);

    expect(cancelStatusChangedRows($checkIn))->toBe($before)
        ->and(UserHistory::query()->count())->toBe(0);
})->with(['completed', 'cancelled']);

it('requires a reason of at most 500 characters', function (mixed $reason): void {
    $checkIn = CheckIn::factory()->pending()->create();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->post(route('dashboard.checkIns.cancel', ['uuid' => $checkIn->uuid]), ['reason' => $reason])
        ->assertSessionHasErrors('reason');

    expect($checkIn->refresh()->status)->toBe(CheckInStatus::Pending)
        ->and(UserHistory::query()->count())->toBe(0);
})->with([
    'missing' => [''],
    'too long' => [str_repeat('a', 501)],
]);

it('shows the cancellation and its reason on the detail page', function (): void {
    $staff = User::factory()->withRole(Role::Admin)->create(['name' => 'Dana Lee']);
    $checkIn = CheckIn::factory()->pending()->create();

    $this->actingAs($staff)->post(route('dashboard.checkIns.cancel', ['uuid' => $checkIn->uuid]), ['reason' => 'Truck broke down']);

    $this->actingAs($staff)
        ->get(route('dashboard.checkIns.show', ['uuid' => $checkIn->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('checkIn.status', 'cancelled')
            ->where('checkIn.cancelledReason', 'Truck broke down')
            ->whereType('checkIn.cancelledAt', 'string')
            ->where('checkIn.history.0.description', 'Status changed from pending to canceled')
            ->where('checkIn.history.0.actor', 'Dana Lee'));
});
