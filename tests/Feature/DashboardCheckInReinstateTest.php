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
 * Reinstating a cancelled check-in from the dashboard: who may do it, the two
 * trails it writes, and the states it refuses.
 */

function reinstateHistoryRows(CheckIn $checkIn, RecordHistoryEvent $event): int
{
    return RecordHistory::query()
        ->where('recordable_id', $checkIn->id)
        ->where('event', $event)
        ->count();
}

it('reinstates a cancelled check-in and logs it on the timeline and the staff trail', function (Role $role): void {
    $location = Location::factory()->create();
    $canceller = User::factory()->withRole(Role::Shipping)->worksAt($location)->create();
    $checkIn = CheckIn::factory()->atLocation($location)->cancelled($canceller, 'Wrong truck')->create();
    $staff = User::factory()->withRole($role)->worksAt($location)->create();
    $statusChangesBefore = reinstateHistoryRows($checkIn, RecordHistoryEvent::StatusChanged);

    $this->actingAs($staff)
        ->from(route('dashboard.checkIns.show', ['uuid' => $checkIn->uuid]))
        ->post(route('dashboard.checkIns.reinstate', ['uuid' => $checkIn->uuid]))
        ->assertRedirect(route('dashboard.checkIns.show', ['uuid' => $checkIn->uuid]));

    $checkIn->refresh();
    expect($checkIn->status)->toBe(CheckInStatus::Pending)
        ->and($checkIn->cancelled_at)->toBeNull()
        ->and($checkIn->cancelled_by)->toBeNull()
        ->and($checkIn->cancelled_reason)->toBeNull()
        ->and(reinstateHistoryRows($checkIn, RecordHistoryEvent::StatusChanged))->toBe($statusChangesBefore + 1);

    $statusChange = RecordHistory::query()->where('event', RecordHistoryEvent::StatusChanged)->latest('id')->first();
    expect($statusChange->recordable_id)->toBe($checkIn->id)
        ->and($statusChange->user_id)->toBe($staff->id)
        ->and($statusChange->context)->toBe(['from' => 'cancelled', 'to' => 'pending']);

    $reinstated = RecordHistory::query()->where('event', RecordHistoryEvent::Reinstated)->sole();
    expect($reinstated->recordable_id)->toBe($checkIn->id)
        ->and($reinstated->user_id)->toBe($staff->id)
        ->and($reinstated->context)->toBe(['cancelled_reason' => 'Wrong truck', 'cancelled_by' => $canceller->id]);

    $trail = UserHistory::query()->sole();
    expect($trail->user_id)->toBe($staff->id)
        ->and($trail->event)->toBe(UserHistoryEvent::CheckInReinstated)
        ->and($trail->changes)->toBe(['check_in' => $checkIn->uuid]);
})->with([
    'admin' => [Role::Admin],
    'shipping manager' => [Role::ShippingManager],
]);

it('forbids other roles from reinstating a check-in', function (Role $role): void {
    $location = Location::factory()->create();
    $checkIn = CheckIn::factory()->atLocation($location)->cancelled()->create();

    $this->actingAs(User::factory()->withRole($role)->worksAt($location)->create())
        ->post(route('dashboard.checkIns.reinstate', ['uuid' => $checkIn->uuid]))
        ->assertForbidden();

    expect($checkIn->refresh()->status)->toBe(CheckInStatus::Cancelled)
        ->and(reinstateHistoryRows($checkIn, RecordHistoryEvent::Reinstated))->toBe(0)
        ->and(UserHistory::query()->count())->toBe(0);
})->with([
    'shipping' => [Role::Shipping],
    'sales' => [Role::Sales],
    'driver' => [Role::Driver],
]);

it('sends a guest to sign in before reinstating a check-in', function (): void {
    $checkIn = CheckIn::factory()->cancelled()->create();

    $this->post(route('dashboard.checkIns.reinstate', ['uuid' => $checkIn->uuid]))
        ->assertRedirect(route('login'));

    expect($checkIn->refresh()->status)->toBe(CheckInStatus::Cancelled);
});

it('returns not found for a check-in at a location the user does not work', function (): void {
    $checkIn = CheckIn::factory()->cancelled()->create();
    $manager = User::factory()->withRole(Role::ShippingManager)->worksAt(Location::factory()->create())->create();

    $this->actingAs($manager)
        ->post(route('dashboard.checkIns.reinstate', ['uuid' => $checkIn->uuid]))
        ->assertNotFound();

    expect($checkIn->refresh()->status)->toBe(CheckInStatus::Cancelled)
        ->and(UserHistory::query()->count())->toBe(0);
});

it('returns not found for an archived check-in', function (): void {
    $checkIn = CheckIn::factory()->cancelled()->create();
    $checkIn->delete();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->post(route('dashboard.checkIns.reinstate', ['uuid' => $checkIn->uuid]))
        ->assertNotFound();

    expect(CheckIn::withTrashed()->find($checkIn->id)->status)->toBe(CheckInStatus::Cancelled);
});

it('refuses a check-in that is not cancelled', function (string $state): void {
    $checkIn = CheckIn::factory()->{$state}()->create();
    $statusChangesBefore = reinstateHistoryRows($checkIn, RecordHistoryEvent::StatusChanged);

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->post(route('dashboard.checkIns.reinstate', ['uuid' => $checkIn->uuid]))
        ->assertSessionHasErrors(['status' => 'This check-in is not cancelled.']);

    expect(reinstateHistoryRows($checkIn, RecordHistoryEvent::StatusChanged))->toBe($statusChangesBefore)
        ->and(reinstateHistoryRows($checkIn, RecordHistoryEvent::Reinstated))->toBe(0)
        ->and(UserHistory::query()->count())->toBe(0);
})->with(['pending', 'completed']);

it('shows the reinstatement and the cancellation reason on the timeline', function (): void {
    $staff = User::factory()->withRole(Role::Admin)->create(['name' => 'Dana Lee']);
    $checkIn = CheckIn::factory()->cancelled()->create();

    $this->actingAs($staff)->post(route('dashboard.checkIns.reinstate', ['uuid' => $checkIn->uuid]));

    $this->actingAs($staff)
        ->get(route('dashboard.checkIns.show', ['uuid' => $checkIn->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('checkIn.status', 'pending')
            ->where('checkIn.cancelledReason', null)
            ->where('checkIn.history', fn ($history): bool => collect($history)
                ->where('actor', 'Dana Lee')
                ->pluck('description')
                ->intersect(['Reinstated (was canceled: Load canceled)', 'Status changed from canceled to pending'])
                ->count() === 2));
});
