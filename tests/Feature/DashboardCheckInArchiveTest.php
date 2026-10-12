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
 * Archiving a completed or canceled check-in from the dashboard: who may do
 * it, the two trails it writes, the broadcast, and the states it refuses.
 */

function archiveHistoryRows(CheckIn $checkIn): int
{
    return RecordHistory::query()
        ->where('recordable_id', $checkIn->id)
        ->where('event', RecordHistoryEvent::Archived)
        ->count();
}

it('archives a check-in and logs it on the timeline and the staff trail', function (Role $role, string $state): void {
    Event::fake([CheckInUpdated::class]);
    $location = Location::factory()->create();
    $checkIn = CheckIn::factory()->atLocation($location)->{$state}()->create();
    $staff = User::factory()->withRole($role)->worksAt($location)->create();
    $statusBefore = $checkIn->status;

    $this->actingAs($staff)
        ->from(route('dashboard.checkIns.show', ['uuid' => $checkIn->uuid]))
        ->post(route('dashboard.checkIns.archive', ['uuid' => $checkIn->uuid]), ['reason' => 'Duplicate entry'])
        ->assertRedirect(route('dashboard.checkIns.show', ['uuid' => $checkIn->uuid]));

    $checkIn = CheckIn::withTrashed()->find($checkIn->id);
    expect($checkIn->trashed())->toBeTrue()
        ->and($checkIn->status)->toBe($statusBefore);

    $archived = RecordHistory::query()->where('event', RecordHistoryEvent::Archived)->sole();
    expect($archived->recordable_id)->toBe($checkIn->id)
        ->and($archived->user_id)->toBe($staff->id)
        ->and($archived->context)->toBe(['reason' => 'Duplicate entry']);

    $trail = UserHistory::query()->sole();
    expect($trail->user_id)->toBe($staff->id)
        ->and($trail->event)->toBe(UserHistoryEvent::CheckInArchived)
        ->and($trail->changes)->toBe(['check_in' => $checkIn->uuid, 'reason' => 'Duplicate entry']);

    Event::assertDispatched(CheckInUpdated::class, fn (CheckInUpdated $event): bool => $event->checkIn->is($checkIn));
})->with([
    'admin' => [Role::Admin],
    'shipping manager' => [Role::ShippingManager],
    'shipping' => [Role::Shipping],
])->with(['completed', 'cancelled']);

it('archives without a reason', function (?string $reason): void {
    $checkIn = CheckIn::factory()->completed()->create();
    $staff = User::factory()->withRole(Role::Admin)->create();

    $this->actingAs($staff)
        ->post(route('dashboard.checkIns.archive', ['uuid' => $checkIn->uuid]), ['reason' => $reason])
        ->assertSessionHasNoErrors();

    expect(CheckIn::withTrashed()->find($checkIn->id)->trashed())->toBeTrue()
        ->and(RecordHistory::query()->where('event', RecordHistoryEvent::Archived)->sole()->context)->toBeNull()
        ->and(UserHistory::query()->sole()->changes)->toBe(['check_in' => $checkIn->uuid]);
})->with(['null' => [null], 'blank' => ['   ']]);

it('forbids other roles from archiving a check-in', function (Role $role): void {
    $location = Location::factory()->create();
    $checkIn = CheckIn::factory()->atLocation($location)->completed()->create();

    $this->actingAs(User::factory()->withRole($role)->worksAt($location)->create())
        ->post(route('dashboard.checkIns.archive', ['uuid' => $checkIn->uuid]))
        ->assertForbidden();

    expect($checkIn->refresh()->trashed())->toBeFalse()
        ->and(archiveHistoryRows($checkIn))->toBe(0)
        ->and(UserHistory::query()->count())->toBe(0);
})->with([
    'sales' => [Role::Sales],
    'driver' => [Role::Driver],
]);

it('sends a guest to sign in before archiving a check-in', function (): void {
    $checkIn = CheckIn::factory()->completed()->create();

    $this->post(route('dashboard.checkIns.archive', ['uuid' => $checkIn->uuid]))
        ->assertRedirect(route('login'));

    expect($checkIn->refresh()->trashed())->toBeFalse();
});

it('returns not found for a check-in at a location the user does not work', function (): void {
    $checkIn = CheckIn::factory()->completed()->create();
    $manager = User::factory()->withRole(Role::ShippingManager)->worksAt(Location::factory()->create())->create();

    $this->actingAs($manager)
        ->post(route('dashboard.checkIns.archive', ['uuid' => $checkIn->uuid]))
        ->assertNotFound();

    expect($checkIn->refresh()->trashed())->toBeFalse()
        ->and(UserHistory::query()->count())->toBe(0);
});

it('returns not found for a check-in that is already archived', function (): void {
    $checkIn = CheckIn::factory()->completed()->archived()->create();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->post(route('dashboard.checkIns.archive', ['uuid' => $checkIn->uuid]))
        ->assertNotFound();

    expect(archiveHistoryRows($checkIn))->toBe(0)
        ->and(UserHistory::query()->count())->toBe(0);
});

it('refuses a pending check-in', function (): void {
    Event::fake([CheckInUpdated::class]);
    $checkIn = CheckIn::factory()->pending()->create();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->post(route('dashboard.checkIns.archive', ['uuid' => $checkIn->uuid]))
        ->assertSessionHasErrors(['status' => 'Only completed or canceled check-ins can be archived.']);

    expect($checkIn->refresh()->trashed())->toBeFalse()
        ->and($checkIn->status)->toBe(CheckInStatus::Pending)
        ->and(archiveHistoryRows($checkIn))->toBe(0)
        ->and(UserHistory::query()->count())->toBe(0);

    Event::assertNotDispatched(CheckInUpdated::class);
});

it('refuses a reason longer than 500 characters', function (): void {
    $checkIn = CheckIn::factory()->completed()->create();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->post(route('dashboard.checkIns.archive', ['uuid' => $checkIn->uuid]), ['reason' => str_repeat('a', 501)])
        ->assertSessionHasErrors('reason');

    expect($checkIn->refresh()->trashed())->toBeFalse();
});

it('shows the archive and its reason on the timeline', function (): void {
    $staff = User::factory()->withRole(Role::Admin)->create(['name' => 'Dana Lee']);
    $checkIn = CheckIn::factory()->completed()->create();

    $this->actingAs($staff)->post(route('dashboard.checkIns.archive', ['uuid' => $checkIn->uuid]), ['reason' => 'Duplicate entry']);

    $this->actingAs($staff)
        ->get(route('dashboard.checkIns.show', ['uuid' => $checkIn->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->whereNot('checkIn.archivedAt', null)
            ->where('checkIn.history', fn ($history): bool => collect($history)
                ->where('actor', 'Dana Lee')
                ->contains('description', 'Archived (reason: Duplicate entry)')));
});
