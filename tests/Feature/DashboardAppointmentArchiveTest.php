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
 * Archiving an appointment that is no longer scheduled from the dashboard:
 * who may do it, the two trails it writes, the broadcast, and the states it
 * refuses.
 */

function appointmentArchiveHistoryRows(Appointment $appointment): int
{
    return RecordHistory::query()
        ->where('recordable_id', $appointment->id)
        ->where('event', RecordHistoryEvent::Archived)
        ->count();
}

it('archives an appointment and logs it on the timeline and the staff trail', function (Role $role, string $state): void {
    Event::fake([AppointmentUpdated::class]);
    $location = Location::factory()->create();
    $appointment = Appointment::factory()->atLocation($location)->{$state}()->create();
    $staff = User::factory()->withRole($role)->worksAt($location)->create();
    $statusBefore = $appointment->status;

    $this->actingAs($staff)
        ->from(route('dashboard.appointments.show', ['uuid' => $appointment->uuid]))
        ->post(route('dashboard.appointments.archive', ['uuid' => $appointment->uuid]), ['reason' => 'Duplicate booking'])
        ->assertRedirect(route('dashboard.appointments.show', ['uuid' => $appointment->uuid]));

    $appointment = Appointment::withTrashed()->find($appointment->id);
    expect($appointment->trashed())->toBeTrue()
        ->and($appointment->status)->toBe($statusBefore);

    $archived = RecordHistory::query()->where('event', RecordHistoryEvent::Archived)->sole();
    expect($archived->recordable_id)->toBe($appointment->id)
        ->and($archived->user_id)->toBe($staff->id)
        ->and($archived->context)->toBe(['reason' => 'Duplicate booking']);

    $trail = UserHistory::query()->sole();
    expect($trail->user_id)->toBe($staff->id)
        ->and($trail->event)->toBe(UserHistoryEvent::AppointmentArchived)
        ->and($trail->changes)->toBe(['appointment' => $appointment->uuid, 'reason' => 'Duplicate booking']);

    Event::assertDispatched(AppointmentUpdated::class, fn (AppointmentUpdated $event): bool => $event->appointment->is($appointment));
})->with([
    'admin' => [Role::Admin],
    'shipping manager' => [Role::ShippingManager],
    'shipping' => [Role::Shipping],
])->with(['cancelled', 'completed', 'noShow', 'checkedIn']);

it('archives without a reason', function (?string $reason): void {
    $appointment = Appointment::factory()->completed()->create();
    $staff = User::factory()->withRole(Role::Admin)->create();

    $this->actingAs($staff)
        ->post(route('dashboard.appointments.archive', ['uuid' => $appointment->uuid]), ['reason' => $reason])
        ->assertSessionHasNoErrors();

    expect(Appointment::withTrashed()->find($appointment->id)->trashed())->toBeTrue()
        ->and(RecordHistory::query()->where('event', RecordHistoryEvent::Archived)->sole()->context)->toBeNull()
        ->and(UserHistory::query()->sole()->changes)->toBe(['appointment' => $appointment->uuid]);
})->with(['null' => [null], 'blank' => ['   ']]);

it('forbids other roles from archiving an appointment', function (Role $role): void {
    $location = Location::factory()->create();
    $appointment = Appointment::factory()->atLocation($location)->completed()->create();

    $this->actingAs(User::factory()->withRole($role)->worksAt($location)->create())
        ->post(route('dashboard.appointments.archive', ['uuid' => $appointment->uuid]))
        ->assertForbidden();

    expect($appointment->refresh()->trashed())->toBeFalse()
        ->and(appointmentArchiveHistoryRows($appointment))->toBe(0)
        ->and(UserHistory::query()->count())->toBe(0);
})->with([
    'sales' => [Role::Sales],
    'driver' => [Role::Driver],
]);

it('sends a guest to sign in before archiving an appointment', function (): void {
    $appointment = Appointment::factory()->completed()->create();

    $this->post(route('dashboard.appointments.archive', ['uuid' => $appointment->uuid]))
        ->assertRedirect(route('login'));

    expect($appointment->refresh()->trashed())->toBeFalse();
});

it('returns not found for an appointment at a location the user does not work', function (): void {
    $appointment = Appointment::factory()->completed()->create();
    $manager = User::factory()->withRole(Role::ShippingManager)->worksAt(Location::factory()->create())->create();

    $this->actingAs($manager)
        ->post(route('dashboard.appointments.archive', ['uuid' => $appointment->uuid]))
        ->assertNotFound();

    expect($appointment->refresh()->trashed())->toBeFalse()
        ->and(UserHistory::query()->count())->toBe(0);
});

it('returns not found for an appointment that is already archived', function (): void {
    $appointment = Appointment::factory()->completed()->archived()->create();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->post(route('dashboard.appointments.archive', ['uuid' => $appointment->uuid]))
        ->assertNotFound();

    expect(appointmentArchiveHistoryRows($appointment))->toBe(0)
        ->and(UserHistory::query()->count())->toBe(0);
});

it('refuses a scheduled appointment', function (): void {
    Event::fake([AppointmentUpdated::class]);
    $appointment = Appointment::factory()->scheduled()->create();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->post(route('dashboard.appointments.archive', ['uuid' => $appointment->uuid]))
        ->assertSessionHasErrors(['status' => 'Scheduled appointments must be canceled before they can be archived.']);

    expect($appointment->refresh()->trashed())->toBeFalse()
        ->and($appointment->status)->toBe(AppointmentStatus::Scheduled)
        ->and(appointmentArchiveHistoryRows($appointment))->toBe(0)
        ->and(UserHistory::query()->count())->toBe(0);

    Event::assertNotDispatched(AppointmentUpdated::class);
});

it('refuses a reason longer than 500 characters', function (): void {
    $appointment = Appointment::factory()->completed()->create();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->post(route('dashboard.appointments.archive', ['uuid' => $appointment->uuid]), ['reason' => str_repeat('a', 501)])
        ->assertSessionHasErrors('reason');

    expect($appointment->refresh()->trashed())->toBeFalse();
});

it('shows the archive and its reason on the timeline', function (): void {
    $staff = User::factory()->withRole(Role::Admin)->create(['name' => 'Dana Lee']);
    $appointment = Appointment::factory()->completed()->create();

    $this->actingAs($staff)->post(route('dashboard.appointments.archive', ['uuid' => $appointment->uuid]), ['reason' => 'Duplicate booking']);

    $this->actingAs($staff)
        ->get(route('dashboard.appointments.show', ['uuid' => $appointment->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->whereNot('appointment.archivedAt', null)
            ->where('appointment.history', fn ($history): bool => collect($history)
                ->where('actor', 'Dana Lee')
                ->contains('description', 'Archived (reason: Duplicate booking)')));
});
