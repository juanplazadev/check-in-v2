<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Enums\RecordHistoryEvent;
use App\Enums\Role;
use App\Enums\UserHistoryEvent;
use App\Models\Appointment;
use App\Models\Location;
use App\Models\RecordHistory;
use App\Models\User;
use App\Models\UserHistory;
use Inertia\Testing\AssertableInertia;

/*
 * Reinstating a cancelled appointment from the dashboard: who may do it, the
 * two trails it writes, and the states it refuses.
 */

function appointmentReinstateHistoryRows(Appointment $appointment, RecordHistoryEvent $event): int
{
    return RecordHistory::query()
        ->where('recordable_type', $appointment->getMorphClass())
        ->where('recordable_id', $appointment->id)
        ->where('event', $event)
        ->count();
}

it('reinstates a cancelled appointment and logs it on the timeline and the staff trail', function (Role $role): void {
    $location = Location::factory()->create();
    $canceller = User::factory()->withRole(Role::Shipping)->worksAt($location)->create();
    $appointment = Appointment::factory()->atLocation($location)->cancelled('Wrong truck', $canceller)->create();
    $staff = User::factory()->withRole($role)->worksAt($location)->create();
    $statusChangesBefore = appointmentReinstateHistoryRows($appointment, RecordHistoryEvent::StatusChanged);

    $this->actingAs($staff)
        ->from(route('dashboard.appointments.show', ['uuid' => $appointment->uuid]))
        ->post(route('dashboard.appointments.reinstate', ['uuid' => $appointment->uuid]))
        ->assertRedirect(route('dashboard.appointments.show', ['uuid' => $appointment->uuid]));

    $appointment->refresh();
    expect($appointment->status)->toBe(AppointmentStatus::Scheduled)
        ->and($appointment->cancelled_at)->toBeNull()
        ->and($appointment->cancelled_by)->toBeNull()
        ->and($appointment->cancelled_reason)->toBeNull()
        ->and(appointmentReinstateHistoryRows($appointment, RecordHistoryEvent::StatusChanged))->toBe($statusChangesBefore + 1);

    $statusChange = RecordHistory::query()->where('event', RecordHistoryEvent::StatusChanged)->latest('id')->first();
    expect($statusChange->recordable_id)->toBe($appointment->id)
        ->and($statusChange->user_id)->toBe($staff->id)
        ->and($statusChange->context)->toBe(['from' => 'cancelled', 'to' => 'scheduled']);

    $reinstated = RecordHistory::query()->where('event', RecordHistoryEvent::Reinstated)->sole();
    expect($reinstated->recordable_id)->toBe($appointment->id)
        ->and($reinstated->user_id)->toBe($staff->id)
        ->and($reinstated->context)->toBe(['cancelled_reason' => 'Wrong truck', 'cancelled_by' => $canceller->id]);

    $trail = UserHistory::query()->sole();
    expect($trail->user_id)->toBe($staff->id)
        ->and($trail->event)->toBe(UserHistoryEvent::AppointmentReinstated)
        ->and($trail->changes)->toBe(['appointment' => $appointment->uuid]);
})->with([
    'admin' => [Role::Admin],
    'shipping manager' => [Role::ShippingManager],
]);

it('reinstates an appointment whose time slot has already passed', function (): void {
    $appointment = Appointment::factory()->cancelled()->scheduledFor(now()->subDay())->create();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->post(route('dashboard.appointments.reinstate', ['uuid' => $appointment->uuid]))
        ->assertSessionHasNoErrors();

    expect($appointment->refresh()->status)->toBe(AppointmentStatus::Scheduled);
});

it('forbids other roles from reinstating an appointment', function (Role $role): void {
    $location = Location::factory()->create();
    $appointment = Appointment::factory()->atLocation($location)->cancelled()->create();

    $this->actingAs(User::factory()->withRole($role)->worksAt($location)->create())
        ->post(route('dashboard.appointments.reinstate', ['uuid' => $appointment->uuid]))
        ->assertForbidden();

    expect($appointment->refresh()->status)->toBe(AppointmentStatus::Cancelled)
        ->and(appointmentReinstateHistoryRows($appointment, RecordHistoryEvent::Reinstated))->toBe(0)
        ->and(UserHistory::query()->count())->toBe(0);
})->with([
    'shipping' => [Role::Shipping],
    'sales' => [Role::Sales],
    'driver' => [Role::Driver],
]);

it('sends a guest to sign in before reinstating an appointment', function (): void {
    $appointment = Appointment::factory()->cancelled()->create();

    $this->post(route('dashboard.appointments.reinstate', ['uuid' => $appointment->uuid]))
        ->assertRedirect(route('login'));

    expect($appointment->refresh()->status)->toBe(AppointmentStatus::Cancelled);
});

it('returns not found for an appointment at a location the user does not work', function (): void {
    $appointment = Appointment::factory()->cancelled()->create();
    $manager = User::factory()->withRole(Role::ShippingManager)->worksAt(Location::factory()->create())->create();

    $this->actingAs($manager)
        ->post(route('dashboard.appointments.reinstate', ['uuid' => $appointment->uuid]))
        ->assertNotFound();

    expect($appointment->refresh()->status)->toBe(AppointmentStatus::Cancelled)
        ->and(UserHistory::query()->count())->toBe(0);
});

it('returns not found for an archived appointment', function (): void {
    $appointment = Appointment::factory()->cancelled()->create();
    $appointment->delete();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->post(route('dashboard.appointments.reinstate', ['uuid' => $appointment->uuid]))
        ->assertNotFound();

    expect(Appointment::withTrashed()->find($appointment->id)->status)->toBe(AppointmentStatus::Cancelled);
});

it('refuses an appointment that is not cancelled', function (string $state): void {
    $appointment = Appointment::factory()->{$state}()->create();
    $statusChangesBefore = appointmentReinstateHistoryRows($appointment, RecordHistoryEvent::StatusChanged);

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->post(route('dashboard.appointments.reinstate', ['uuid' => $appointment->uuid]))
        ->assertSessionHasErrors(['status' => 'This appointment is not cancelled.']);

    expect(appointmentReinstateHistoryRows($appointment, RecordHistoryEvent::StatusChanged))->toBe($statusChangesBefore)
        ->and(appointmentReinstateHistoryRows($appointment, RecordHistoryEvent::Reinstated))->toBe(0)
        ->and(UserHistory::query()->count())->toBe(0);
})->with(['scheduled', 'completed']);

it('shows the reinstatement and the cancellation reason on the timeline', function (): void {
    $staff = User::factory()->withRole(Role::Admin)->create(['name' => 'Dana Lee']);
    $appointment = Appointment::factory()->cancelled('Load canceled')->create();

    $this->actingAs($staff)->post(route('dashboard.appointments.reinstate', ['uuid' => $appointment->uuid]));

    $this->actingAs($staff)
        ->get(route('dashboard.appointments.show', ['uuid' => $appointment->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('appointment.status', 'scheduled')
            ->where('appointment.cancelledReason', null)
            ->where('appointment.history', fn ($history): bool => collect($history)
                ->where('actor', 'Dana Lee')
                ->pluck('description')
                ->intersect(['Reinstated (was canceled: Load canceled)', 'Status changed from canceled to scheduled'])
                ->count() === 2));
});
