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
 * Completing a check-in from the dashboard: who may do it, the two trails it
 * writes, and the states it refuses.
 */

function statusChangedRows(CheckIn $checkIn): int
{
    return RecordHistory::query()
        ->where('recordable_id', $checkIn->id)
        ->where('event', RecordHistoryEvent::StatusChanged)
        ->count();
}

it('completes a pending check-in and logs it on the timeline and the staff trail', function (Role $role): void {
    $location = Location::factory()->create();
    $checkIn = CheckIn::factory()->atLocation($location)->create();
    $staff = User::factory()->withRole($role)->worksAt($location)->create();

    $this->actingAs($staff)
        ->from(route('dashboard.checkIns.show', ['uuid' => $checkIn->uuid]))
        ->post(route('dashboard.checkIns.complete', ['uuid' => $checkIn->uuid]))
        ->assertRedirect(route('dashboard.checkIns.show', ['uuid' => $checkIn->uuid]));

    $checkIn->refresh();
    expect($checkIn->status)->toBe(CheckInStatus::Completed)
        ->and($checkIn->completed_at)->not->toBeNull()
        ->and($checkIn->completed_by)->toBe($staff->id);

    $entry = RecordHistory::query()->where('event', RecordHistoryEvent::StatusChanged)->sole();
    expect($entry->recordable_id)->toBe($checkIn->id)
        ->and($entry->subject)->toBe('status')
        ->and($entry->user_id)->toBe($staff->id)
        ->and($entry->context)->toBe(['from' => 'pending', 'to' => 'completed']);

    $trail = UserHistory::query()->sole();
    expect($trail->user_id)->toBe($staff->id)
        ->and($trail->event)->toBe(UserHistoryEvent::CheckInCompleted)
        ->and($trail->changes)->toBe(['check_in' => $checkIn->uuid]);
})->with([
    'admin' => [Role::Admin],
    'shipping manager' => [Role::ShippingManager],
    'shipping' => [Role::Shipping],
]);

it('forbids sales and drivers from completing a check-in', function (Role $role): void {
    $checkIn = CheckIn::factory()->create();

    $this->actingAs(User::factory()->withRole($role)->create())
        ->post(route('dashboard.checkIns.complete', ['uuid' => $checkIn->uuid]))
        ->assertForbidden();

    expect($checkIn->refresh()->status)->toBe(CheckInStatus::Pending)
        ->and(UserHistory::query()->count())->toBe(0);
})->with([
    'sales' => [Role::Sales],
    'driver' => [Role::Driver],
]);

it('sends a guest to sign in before completing a check-in', function (): void {
    $checkIn = CheckIn::factory()->create();

    $this->post(route('dashboard.checkIns.complete', ['uuid' => $checkIn->uuid]))
        ->assertRedirect(route('login'));

    expect($checkIn->refresh()->status)->toBe(CheckInStatus::Pending);
});

it('returns not found for a check-in at a location the user does not work', function (): void {
    $checkIn = CheckIn::factory()->create();
    $shipping = User::factory()->withRole(Role::Shipping)->worksAt(Location::factory()->create())->create();

    $this->actingAs($shipping)
        ->post(route('dashboard.checkIns.complete', ['uuid' => $checkIn->uuid]))
        ->assertNotFound();

    expect($checkIn->refresh()->status)->toBe(CheckInStatus::Pending)
        ->and(UserHistory::query()->count())->toBe(0);
});

it('returns not found for an archived check-in', function (): void {
    $checkIn = CheckIn::factory()->create();
    $checkIn->delete();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->post(route('dashboard.checkIns.complete', ['uuid' => $checkIn->uuid]))
        ->assertNotFound();

    expect(CheckIn::withTrashed()->find($checkIn->id)->status)->toBe(CheckInStatus::Pending);
});

it('refuses a check-in that is no longer pending', function (string $state): void {
    $checkIn = CheckIn::factory()->{$state}()->create();
    $before = statusChangedRows($checkIn);

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->post(route('dashboard.checkIns.complete', ['uuid' => $checkIn->uuid]))
        ->assertSessionHasErrors(['status' => 'This check-in is no longer pending.']);

    expect(statusChangedRows($checkIn))->toBe($before)
        ->and(UserHistory::query()->count())->toBe(0);
})->with(['completed', 'cancelled']);

it('shows the completion on the timeline with the staff member as actor', function (): void {
    $staff = User::factory()->withRole(Role::Admin)->create(['name' => 'Dana Lee']);
    $checkIn = CheckIn::factory()->create();

    $this->actingAs($staff)->post(route('dashboard.checkIns.complete', ['uuid' => $checkIn->uuid]));

    $this->actingAs($staff)
        ->get(route('dashboard.checkIns.show', ['uuid' => $checkIn->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('checkIn.status', 'completed')
            ->where('checkIn.history.0.description', 'Status changed from pending to completed')
            ->where('checkIn.history.0.actor', 'Dana Lee'));
});
