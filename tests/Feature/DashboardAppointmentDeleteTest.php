<?php

declare(strict_types=1);

use App\Dashboard\BroadcastChannels;
use App\Enums\AppointmentLinkMethod;
use App\Enums\RecordHistoryEvent;
use App\Enums\Role;
use App\Enums\UserHistoryEvent;
use App\Events\AppointmentDeleted;
use App\Events\CheckInUpdated;
use App\Models\Appointment;
use App\Models\AppointmentNote;
use App\Models\CheckIn;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\RecordHistory;
use App\Models\ShortLink;
use App\Models\User;
use App\Models\UserHistory;
use App\Notifications\AppointmentReceived;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia;

/*
 * Permanently deleting an appointment from the dashboard: every child row goes
 * with it, the deletion is logged where it survives, and only admins may do
 * it, in any status.
 */

/**
 * An appointment with one of every kind of child row the delete must remove.
 */
function appointmentWithChildren(Appointment $appointment, User $staff): Appointment
{
    RecordHistory::factory()->forRecord($appointment)->count(2)->create();
    AppointmentNote::factory()->for($appointment)->create();
    $appointment->purchaseOrders()->save(PurchaseOrder::factory()->make());
    $appointment->shortUrl();
    $staff->notifyNow(new AppointmentReceived($appointment), ['database']);

    return $appointment;
}

it('deletes an appointment in any status with every child row and logs it on the staff trail', function (string $state): void {
    Event::fake([AppointmentDeleted::class]);
    $location = Location::factory()->create();
    $admin = User::factory()->withRole(Role::Admin)->worksAt($location)->create();
    $appointment = Appointment::factory()->atLocation($location)->{$state === 'archived' ? 'scheduled' : $state}()->create();
    if ($state === 'archived') {
        $appointment->delete();
    }

    $appointment = appointmentWithChildren($appointment, $admin);
    $untouched = appointmentWithChildren(Appointment::factory()->atLocation($location)->create(), $admin);
    $pendingClaim = User::factory()->create(['pending_appointment_id' => $appointment->id]);

    $this->actingAs($admin)
        ->delete(route('dashboard.appointments.destroy', ['uuid' => $appointment->uuid]), ['reason' => 'Test entry'])
        ->assertRedirect(route('dashboard.appointments'))
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => sprintf('Appointment %s was permanently deleted.', $appointment->reference_number),
        ]);

    expect(Appointment::withTrashed()->find($appointment->id))->toBeNull()
        ->and(RecordHistory::query()->where('recordable_id', $appointment->id)->where('recordable_type', 'appointment')->count())->toBe(0)
        ->and(AppointmentNote::query()->where('appointment_id', $appointment->id)->count())->toBe(0)
        ->and(PurchaseOrder::query()->where('purchasable_id', $appointment->id)->where('purchasable_type', 'appointment')->count())->toBe(0)
        ->and(ShortLink::query()->where('linkable_id', $appointment->id)->where('linkable_type', 'appointment')->count())->toBe(0)
        ->and(DatabaseNotification::query()->count())->toBe(1)
        ->and(DatabaseNotification::query()->sole()->data['appointmentUuid'])->toBe($untouched->uuid)
        ->and($pendingClaim->refresh()->pending_appointment_id)->toBeNull();

    expect(RecordHistory::query()->where('recordable_id', $untouched->id)->count())->toBe(2)
        ->and($untouched->notes()->count())->toBe(1)
        ->and($untouched->purchaseOrders()->count())->toBe(1)
        ->and($untouched->shortLink()->exists())->toBeTrue();

    $trail = UserHistory::query()->sole();
    expect($trail->user_id)->toBe($admin->id)
        ->and($trail->event)->toBe(UserHistoryEvent::AppointmentDeleted)
        ->and($trail->changes)->toBe([
            'appointment' => $appointment->uuid,
            'reference_number' => $appointment->reference_number,
            'location' => $location->name,
            'status' => $appointment->status->value,
            'scheduled_for' => $appointment->scheduled_for->toIso8601String(),
            'archived' => $state === 'archived',
            'check_in' => null,
            'reason' => 'Test entry',
        ]);

    Event::assertDispatched(AppointmentDeleted::class, fn (AppointmentDeleted $event): bool => $event->uuid === $appointment->uuid
        && $event->locationId === $location->id);
})->with(['scheduled', 'completed', 'cancelled', 'noShow', 'checkedIn', 'archived']);

it('broadcasts the deleted uuid to the staff who work its location', function (): void {
    $event = new AppointmentDeleted('9a1b2c3d-0000-4000-8000-000000000000', 7);

    expect($event->broadcastOn())->toEqual(BroadcastChannels::appointmentsAt(7))
        ->and($event->broadcastAs())->toBe('appointment.deleted')
        ->and($event->broadcastWith())->toBe(['uuid' => '9a1b2c3d-0000-4000-8000-000000000000']);
});

it('keeps a linked check-in, unlinks it and notes the deletion on its timeline', function (AppointmentLinkMethod $via): void {
    Event::fake([AppointmentDeleted::class, CheckInUpdated::class]);
    $admin = User::factory()->withRole(Role::Admin)->create();
    $appointment = Appointment::factory()->checkedIn()->create();
    $checkIn = CheckIn::factory()->linkedToAppointment($appointment, $via, $admin)->create();

    $this->actingAs($admin)
        ->delete(route('dashboard.appointments.destroy', ['uuid' => $appointment->uuid]), ['reason' => 'Duplicate'])
        ->assertRedirect(route('dashboard.appointments'));

    $checkIn->refresh();
    $entry = $checkIn->history()->sole();

    expect($checkIn->appointment_id)->toBeNull()
        ->and($checkIn->appointment_linked_at)->toBeNull()
        ->and($checkIn->appointment_linked_via)->toBeNull()
        ->and($checkIn->appointment_linked_by)->toBeNull()
        ->and($entry->event)->toBe(RecordHistoryEvent::AppointmentDeleted)
        ->and($entry->user_id)->toBe($admin->id)
        ->and($entry->context)->toBe(['reference_number' => $appointment->reference_number, 'reason' => 'Duplicate'])
        ->and(UserHistory::query()->sole()->changes['check_in'])->toBe($checkIn->uuid);

    Event::assertDispatched(CheckInUpdated::class, fn (CheckInUpdated $event): bool => $event->checkIn->is($checkIn));
})->with([AppointmentLinkMethod::Conversion, AppointmentLinkMethod::Staff]);

it('requires a reason of at most 500 characters', function (mixed $reason): void {
    Event::fake([AppointmentDeleted::class]);
    $appointment = Appointment::factory()->create();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->delete(route('dashboard.appointments.destroy', ['uuid' => $appointment->uuid]), ['reason' => $reason])
        ->assertSessionHasErrors('reason')
        ->assertInertiaFlashMissing('toast');

    expect(Appointment::query()->find($appointment->id))->not->toBeNull()
        ->and(UserHistory::query()->count())->toBe(0);

    Event::assertNotDispatched(AppointmentDeleted::class);
})->with([
    'missing' => [null],
    'blank' => ['   '],
    'too long' => [str_repeat('a', 501)],
]);

it('forbids every other role from deleting an appointment', function (Role $role): void {
    $location = Location::factory()->create();
    $appointment = Appointment::factory()->atLocation($location)->create();

    $this->actingAs(User::factory()->withRole($role)->worksAt($location)->create())
        ->delete(route('dashboard.appointments.destroy', ['uuid' => $appointment->uuid]), ['reason' => 'Test entry'])
        ->assertForbidden();

    expect(Appointment::query()->find($appointment->id))->not->toBeNull()
        ->and(UserHistory::query()->count())->toBe(0);
})->with([
    'shipping manager' => [Role::ShippingManager],
    'shipping' => [Role::Shipping],
    'sales' => [Role::Sales],
    'driver' => [Role::Driver],
]);

it('sends a guest to sign in before deleting an appointment', function (): void {
    $appointment = Appointment::factory()->create();

    $this->delete(route('dashboard.appointments.destroy', ['uuid' => $appointment->uuid]), ['reason' => 'Test entry'])
        ->assertRedirect(route('login'));

    expect(Appointment::query()->find($appointment->id))->not->toBeNull();
});

it('returns not found for an unknown appointment', function (): void {
    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->delete(route('dashboard.appointments.destroy', ['uuid' => fake()->uuid()]), ['reason' => 'Test entry'])
        ->assertNotFound();

    expect(UserHistory::query()->count())->toBe(0);
});

it('sends the deletion impact only on a partial reload, and only to admins', function (): void {
    $location = Location::factory()->create();
    $admin = User::factory()->withRole(Role::Admin)->worksAt($location)->create();
    $appointment = appointmentWithChildren(Appointment::factory()->atLocation($location)->checkedIn()->create(), $admin);
    $checkIn = CheckIn::factory()->atLocation($location)->linkedToAppointment($appointment)->create();
    User::factory()->create(['pending_appointment_id' => $appointment->id]);

    $this->actingAs($admin)
        ->get(route('dashboard.appointments.show', ['uuid' => $appointment->uuid]))
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
                    'checkInReference' => $checkIn->reference_number,
                    'driverHasAccount' => $appointment->user_id !== null,
                    'archived' => false,
                ])));

    $this->actingAs(User::factory()->withRole(Role::ShippingManager)->worksAt($location)->create())
        ->get(route('dashboard.appointments.show', ['uuid' => $appointment->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('deletionImpact', null));
});
