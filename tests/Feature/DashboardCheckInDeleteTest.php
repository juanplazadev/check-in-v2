<?php

declare(strict_types=1);

use App\Dashboard\BroadcastChannels;
use App\Enums\AppointmentStatus;
use App\Enums\RecordHistoryEvent;
use App\Enums\Role;
use App\Enums\UserHistoryEvent;
use App\Events\CheckInDeleted;
use App\Models\Appointment;
use App\Models\CheckIn;
use App\Models\CheckInNote;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\RecordHistory;
use App\Models\ShortLink;
use App\Models\User;
use App\Models\UserHistory;
use App\Notifications\CheckInReceived;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia;

/*
 * Permanently deleting a check-in from the dashboard: every child row goes
 * with it, the deletion is logged where it survives, and only admins may do
 * it, in any status.
 */

/**
 * A check-in with one of every kind of child row the delete must remove.
 */
function checkInWithChildren(CheckIn $checkIn, User $staff): CheckIn
{
    RecordHistory::factory()->forRecord($checkIn)->count(2)->create();
    CheckInNote::factory()->for($checkIn)->create();
    $checkIn->purchaseOrders()->save(PurchaseOrder::factory()->make());
    $checkIn->shortUrl();
    $staff->notifyNow(new CheckInReceived($checkIn), ['database']);

    return $checkIn;
}

it('deletes a check-in in any status with every child row and logs it on the staff trail', function (string $state): void {
    Event::fake([CheckInDeleted::class]);
    $location = Location::factory()->create();
    $admin = User::factory()->withRole(Role::Admin)->worksAt($location)->create();
    $checkIn = checkInWithChildren(CheckIn::factory()->atLocation($location)->{$state}()->create(), $admin);
    $untouched = checkInWithChildren(CheckIn::factory()->atLocation($location)->create(), $admin);
    $pendingClaim = User::factory()->create(['pending_check_in_id' => $checkIn->id]);

    $this->actingAs($admin)
        ->delete(route('dashboard.checkIns.destroy', ['uuid' => $checkIn->uuid]), ['reason' => 'Test entry'])
        ->assertRedirect(route('dashboard.checkIns'));

    expect(CheckIn::withTrashed()->find($checkIn->id))->toBeNull()
        ->and(RecordHistory::query()->where('recordable_id', $checkIn->id)->where('recordable_type', 'check_in')->count())->toBe(0)
        ->and(CheckInNote::query()->where('check_in_id', $checkIn->id)->count())->toBe(0)
        ->and(PurchaseOrder::query()->where('purchasable_id', $checkIn->id)->where('purchasable_type', 'check_in')->count())->toBe(0)
        ->and(ShortLink::query()->where('linkable_id', $checkIn->id)->where('linkable_type', 'check_in')->count())->toBe(0)
        ->and(DatabaseNotification::query()->count())->toBe(1)
        ->and(DatabaseNotification::query()->sole()->data['checkInUuid'])->toBe($untouched->uuid)
        ->and($pendingClaim->refresh()->pending_check_in_id)->toBeNull();

    expect(RecordHistory::query()->where('recordable_id', $untouched->id)->count())->toBe(2)
        ->and($untouched->notes()->count())->toBe(1)
        ->and($untouched->purchaseOrders()->count())->toBe(1)
        ->and($untouched->shortLink()->exists())->toBeTrue();

    $trail = UserHistory::query()->sole();
    expect($trail->user_id)->toBe($admin->id)
        ->and($trail->event)->toBe(UserHistoryEvent::CheckInDeleted)
        ->and($trail->changes)->toBe([
            'check_in' => $checkIn->uuid,
            'reference_number' => $checkIn->reference_number,
            'location' => $location->name,
            'status' => $checkIn->status->value,
            'archived' => $checkIn->trashed(),
            'appointment' => null,
            'reason' => 'Test entry',
        ]);

    Event::assertDispatched(CheckInDeleted::class, fn (CheckInDeleted $event): bool => $event->uuid === $checkIn->uuid
        && $event->locationId === $location->id);
})->with(['pending', 'completed', 'cancelled', 'archived']);

it('broadcasts the deleted uuid to the staff who work its location', function (): void {
    $event = new CheckInDeleted('9a1b2c3d-0000-4000-8000-000000000000', 7);

    expect($event->broadcastOn())->toEqual(BroadcastChannels::checkInsAt(7))
        ->and($event->broadcastAs())->toBe('check-in.deleted')
        ->and($event->broadcastWith())->toBe(['uuid' => '9a1b2c3d-0000-4000-8000-000000000000']);
});

it('leaves a linked appointment as it is and notes the deletion on its timeline', function (): void {
    Event::fake([CheckInDeleted::class]);
    $appointment = Appointment::factory()->checkedIn()->create();
    $checkIn = CheckIn::factory()->linkedToAppointment($appointment)->create();
    $admin = User::factory()->withRole(Role::Admin)->create();

    $this->actingAs($admin)
        ->delete(route('dashboard.checkIns.destroy', ['uuid' => $checkIn->uuid]), ['reason' => 'Duplicate'])
        ->assertRedirect(route('dashboard.checkIns'));

    $appointment->refresh();
    $entry = $appointment->history()->sole();

    expect($appointment->status)->toBe(AppointmentStatus::CheckedIn)
        ->and($appointment->checkIn)->toBeNull()
        ->and($entry->event)->toBe(RecordHistoryEvent::CheckInDeleted)
        ->and($entry->user_id)->toBe($admin->id)
        ->and($entry->context)->toBe(['reference_number' => $checkIn->reference_number, 'reason' => 'Duplicate'])
        ->and(UserHistory::query()->sole()->changes['appointment'])->toBe($appointment->uuid);
});

it('requires a reason of at most 500 characters', function (mixed $reason): void {
    Event::fake([CheckInDeleted::class]);
    $checkIn = CheckIn::factory()->create();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->delete(route('dashboard.checkIns.destroy', ['uuid' => $checkIn->uuid]), ['reason' => $reason])
        ->assertSessionHasErrors('reason');

    expect(CheckIn::query()->find($checkIn->id))->not->toBeNull()
        ->and(UserHistory::query()->count())->toBe(0);

    Event::assertNotDispatched(CheckInDeleted::class);
})->with([
    'missing' => [null],
    'blank' => ['   '],
    'too long' => [str_repeat('a', 501)],
]);

it('forbids every other role from deleting a check-in', function (Role $role): void {
    $location = Location::factory()->create();
    $checkIn = CheckIn::factory()->atLocation($location)->create();

    $this->actingAs(User::factory()->withRole($role)->worksAt($location)->create())
        ->delete(route('dashboard.checkIns.destroy', ['uuid' => $checkIn->uuid]), ['reason' => 'Test entry'])
        ->assertForbidden();

    expect(CheckIn::query()->find($checkIn->id))->not->toBeNull()
        ->and(UserHistory::query()->count())->toBe(0);
})->with([
    'shipping manager' => [Role::ShippingManager],
    'shipping' => [Role::Shipping],
    'sales' => [Role::Sales],
    'driver' => [Role::Driver],
]);

it('sends a guest to sign in before deleting a check-in', function (): void {
    $checkIn = CheckIn::factory()->create();

    $this->delete(route('dashboard.checkIns.destroy', ['uuid' => $checkIn->uuid]), ['reason' => 'Test entry'])
        ->assertRedirect(route('login'));

    expect(CheckIn::query()->find($checkIn->id))->not->toBeNull();
});

it('returns not found for an unknown check-in', function (): void {
    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->delete(route('dashboard.checkIns.destroy', ['uuid' => fake()->uuid()]), ['reason' => 'Test entry'])
        ->assertNotFound();

    expect(UserHistory::query()->count())->toBe(0);
});

it('sends the deletion impact only on a partial reload, and only to admins', function (): void {
    $location = Location::factory()->create();
    $appointment = Appointment::factory()->checkedIn()->create();
    $admin = User::factory()->withRole(Role::Admin)->worksAt($location)->create();
    $checkIn = checkInWithChildren(CheckIn::factory()->atLocation($location)->linkedToAppointment($appointment)->create(), $admin);
    User::factory()->create(['pending_check_in_id' => $checkIn->id]);

    $this->actingAs($admin)
        ->get(route('dashboard.checkIns.show', ['uuid' => $checkIn->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->missing('deletionImpact')
            ->reloadOnly('deletionImpact', fn (AssertableInertia $reload): AssertableInertia => $reload
                ->where('deletionImpact', [
                    'historyEntries' => 2,
                    'notes' => 1,
                    'purchaseOrders' => 1,
                    'staffNotifications' => 1,
                    'hasShortLink' => true,
                    'pendingClaims' => 1,
                    'appointmentReference' => $appointment->reference_number,
                    'driverHasAccount' => $checkIn->user_id !== null,
                    'archived' => false,
                ])));

    $this->actingAs(User::factory()->withRole(Role::ShippingManager)->worksAt($location)->create())
        ->get(route('dashboard.checkIns.show', ['uuid' => $checkIn->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('deletionImpact', null));
});
