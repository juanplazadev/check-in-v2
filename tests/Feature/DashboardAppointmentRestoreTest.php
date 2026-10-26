<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Enums\RecordHistoryEvent;
use App\Enums\Role;
use App\Enums\UserHistoryEvent;
use App\Events\AppointmentUpdated;
use App\Models\Appointment;
use App\Models\Location;
use App\Models\RecordHistory;
use App\Models\User;
use App\Models\UserHistory;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia;

/*
 * Restoring an archived appointment from the dashboard: who may do it, the
 * two trails it writes, the broadcast, and the states it refuses.
 */

function appointmentRestoreHistoryRows(Appointment $appointment): int
{
    return RecordHistory::query()
        ->where('recordable_id', $appointment->id)
        ->where('event', RecordHistoryEvent::Restored)
        ->count();
}

it('restores an archived appointment and logs it on the timeline and the staff trail', function (Role $role): void {
    Event::fake([AppointmentUpdated::class]);
    $location = Location::factory()->create();
    $appointment = Appointment::factory()->atLocation($location)->cancelled()->archived()->create();
    $staff = User::factory()->withRole($role)->worksAt($location)->create();

    $this->actingAs($staff)
        ->from(route('dashboard.appointments.show', ['uuid' => $appointment->uuid]))
        ->post(route('dashboard.appointments.restore', ['uuid' => $appointment->uuid]))
        ->assertRedirect(route('dashboard.appointments.show', ['uuid' => $appointment->uuid]));

    $appointment->refresh();
    expect($appointment->trashed())->toBeFalse()
        ->and($appointment->status)->toBe(AppointmentStatus::Cancelled);

    $restored = RecordHistory::query()->where('event', RecordHistoryEvent::Restored)->sole();
    expect($restored->recordable_id)->toBe($appointment->id)
        ->and($restored->user_id)->toBe($staff->id)
        ->and($restored->context)->toBeNull();

    $trail = UserHistory::query()->sole();
    expect($trail->user_id)->toBe($staff->id)
        ->and($trail->event)->toBe(UserHistoryEvent::AppointmentRestored)
        ->and($trail->changes)->toBe(['appointment' => $appointment->uuid]);

    Event::assertDispatched(AppointmentUpdated::class, fn (AppointmentUpdated $event): bool => $event->appointment->is($appointment));
})->with([
    'admin' => [Role::Admin],
    'shipping manager' => [Role::ShippingManager],
    'shipping' => [Role::Shipping],
]);

it('forbids other roles from restoring an appointment', function (Role $role): void {
    $location = Location::factory()->create();
    $appointment = Appointment::factory()->atLocation($location)->completed()->archived()->create();

    $this->actingAs(User::factory()->withRole($role)->worksAt($location)->create())
        ->post(route('dashboard.appointments.restore', ['uuid' => $appointment->uuid]))
        ->assertForbidden();

    expect(Appointment::withTrashed()->find($appointment->id)->trashed())->toBeTrue()
        ->and(appointmentRestoreHistoryRows($appointment))->toBe(0)
        ->and(UserHistory::query()->count())->toBe(0);
})->with([
    'sales' => [Role::Sales],
    'driver' => [Role::Driver],
]);

it('sends a guest to sign in before restoring an appointment', function (): void {
    $appointment = Appointment::factory()->completed()->archived()->create();

    $this->post(route('dashboard.appointments.restore', ['uuid' => $appointment->uuid]))
        ->assertRedirect(route('login'));

    expect(Appointment::withTrashed()->find($appointment->id)->trashed())->toBeTrue();
});

it('returns not found for an appointment at a location the user does not work', function (): void {
    $appointment = Appointment::factory()->completed()->archived()->create();
    $manager = User::factory()->withRole(Role::ShippingManager)->worksAt(Location::factory()->create())->create();

    $this->actingAs($manager)
        ->post(route('dashboard.appointments.restore', ['uuid' => $appointment->uuid]))
        ->assertNotFound();

    expect(Appointment::withTrashed()->find($appointment->id)->trashed())->toBeTrue()
        ->and(UserHistory::query()->count())->toBe(0);
});

it('returns not found for an appointment that is not archived', function (): void {
    $appointment = Appointment::factory()->completed()->create();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->post(route('dashboard.appointments.restore', ['uuid' => $appointment->uuid]))
        ->assertNotFound();

    expect(appointmentRestoreHistoryRows($appointment))->toBe(0)
        ->and(UserHistory::query()->count())->toBe(0);
});

it('shows the restore on the timeline', function (): void {
    $staff = User::factory()->withRole(Role::Admin)->create(['name' => 'Dana Lee']);
    $appointment = Appointment::factory()->completed()->archived()->create();

    $this->actingAs($staff)->post(route('dashboard.appointments.restore', ['uuid' => $appointment->uuid]));

    $this->actingAs($staff)
        ->get(route('dashboard.appointments.show', ['uuid' => $appointment->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('appointment.archivedAt', null)
            ->where('appointment.history', fn ($history): bool => collect($history)
                ->where('actor', 'Dana Lee')
                ->contains('description', 'Restored from archive')));
});
