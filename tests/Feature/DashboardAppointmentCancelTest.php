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
use App\Notifications\AppointmentCancellation;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

/*
 * Cancelling a scheduled appointment from the dashboard: who may do it, the
 * two trails it writes, the reason it keeps, and the states it refuses.
 */

function appointmentCancelStatusChangedRows(Appointment $appointment): int
{
    return RecordHistory::query()
        ->where('recordable_type', $appointment->getMorphClass())
        ->where('recordable_id', $appointment->id)
        ->where('event', RecordHistoryEvent::StatusChanged)
        ->count();
}

it('cancels a scheduled appointment and logs it on the timeline and the staff trail', function (Role $role): void {
    Notification::fake();
    Mail::fake();

    $location = Location::factory()->create();
    $driver = User::factory()->withRole(Role::Driver)->create();
    $appointment = Appointment::factory()->atLocation($location)->forUser($driver)->scheduled()->create();
    $staff = User::factory()->withRole($role)->worksAt($location)->create();
    $before = appointmentCancelStatusChangedRows($appointment);

    $this->actingAs($staff)
        ->from(route('dashboard.appointments.show', ['uuid' => $appointment->uuid]))
        ->post(route('dashboard.appointments.cancel', ['uuid' => $appointment->uuid]), ['reason' => 'Load canceled by shipper'])
        ->assertRedirect(route('dashboard.appointments.show', ['uuid' => $appointment->uuid]));

    $appointment->refresh();
    expect($appointment->status)->toBe(AppointmentStatus::Cancelled)
        ->and($appointment->cancelled_at)->not->toBeNull()
        ->and($appointment->cancelled_by)->toBe($staff->id)
        ->and($appointment->cancelled_reason)->toBe('Load canceled by shipper')
        ->and(appointmentCancelStatusChangedRows($appointment))->toBe($before + 1);

    $entry = RecordHistory::query()->where('event', RecordHistoryEvent::StatusChanged)->latest('id')->first();
    expect($entry->recordable_id)->toBe($appointment->id)
        ->and($entry->subject)->toBe('status')
        ->and($entry->user_id)->toBe($staff->id)
        ->and($entry->context)->toBe(['from' => 'scheduled', 'to' => 'cancelled']);

    $trail = UserHistory::query()->sole();
    expect($trail->user_id)->toBe($staff->id)
        ->and($trail->event)->toBe(UserHistoryEvent::AppointmentCancelled)
        ->and($trail->changes)->toBe(['appointment' => $appointment->uuid, 'reason' => 'Load canceled by shipper']);

    Notification::assertNotSentTo($driver, AppointmentCancellation::class);
    Mail::assertNothingQueued();
    Mail::assertNothingSent();
})->with([
    'admin' => [Role::Admin],
    'shipping manager' => [Role::ShippingManager],
    'shipping' => [Role::Shipping],
]);

it('forbids other roles from cancelling an appointment', function (Role $role): void {
    $location = Location::factory()->create();
    $appointment = Appointment::factory()->atLocation($location)->scheduled()->create();

    $this->actingAs(User::factory()->withRole($role)->worksAt($location)->create())
        ->post(route('dashboard.appointments.cancel', ['uuid' => $appointment->uuid]), ['reason' => 'No longer needed'])
        ->assertForbidden();

    expect($appointment->refresh()->status)->toBe(AppointmentStatus::Scheduled)
        ->and(UserHistory::query()->count())->toBe(0);
})->with([
    'sales' => [Role::Sales],
    'driver' => [Role::Driver],
]);

it('sends a guest to sign in before cancelling an appointment', function (): void {
    $appointment = Appointment::factory()->scheduled()->create();

    $this->post(route('dashboard.appointments.cancel', ['uuid' => $appointment->uuid]), ['reason' => 'No longer needed'])
        ->assertRedirect(route('login'));

    expect($appointment->refresh()->status)->toBe(AppointmentStatus::Scheduled);
});

it('returns not found for an appointment at a location the user does not work', function (): void {
    $appointment = Appointment::factory()->scheduled()->create();
    $shipping = User::factory()->withRole(Role::Shipping)->worksAt(Location::factory()->create())->create();

    $this->actingAs($shipping)
        ->post(route('dashboard.appointments.cancel', ['uuid' => $appointment->uuid]), ['reason' => 'No longer needed'])
        ->assertNotFound();

    expect($appointment->refresh()->status)->toBe(AppointmentStatus::Scheduled)
        ->and(UserHistory::query()->count())->toBe(0);
});

it('returns not found for an archived appointment', function (): void {
    $appointment = Appointment::factory()->scheduled()->create();
    $appointment->delete();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->post(route('dashboard.appointments.cancel', ['uuid' => $appointment->uuid]), ['reason' => 'No longer needed'])
        ->assertNotFound();

    expect(Appointment::withTrashed()->find($appointment->id)->status)->toBe(AppointmentStatus::Scheduled);
});

it('refuses an appointment that is no longer scheduled', function (string $state): void {
    $appointment = Appointment::factory()->{$state}()->create();
    $before = appointmentCancelStatusChangedRows($appointment);

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->post(route('dashboard.appointments.cancel', ['uuid' => $appointment->uuid]), ['reason' => 'No longer needed'])
        ->assertSessionHasErrors(['status' => 'This appointment is no longer scheduled.']);

    expect(appointmentCancelStatusChangedRows($appointment))->toBe($before)
        ->and(UserHistory::query()->count())->toBe(0);
})->with(['completed', 'cancelled', 'noShow', 'checkedIn']);

it('requires a reason of at most 500 characters', function (mixed $reason): void {
    $appointment = Appointment::factory()->scheduled()->create();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->post(route('dashboard.appointments.cancel', ['uuid' => $appointment->uuid]), ['reason' => $reason])
        ->assertSessionHasErrors('reason');

    expect($appointment->refresh()->status)->toBe(AppointmentStatus::Scheduled)
        ->and(UserHistory::query()->count())->toBe(0);
})->with([
    'missing' => [''],
    'too long' => [str_repeat('a', 501)],
]);

it('shows the cancellation and its reason on the detail page', function (): void {
    $staff = User::factory()->withRole(Role::Admin)->create(['name' => 'Dana Lee']);
    $appointment = Appointment::factory()->scheduled()->create();

    $this->actingAs($staff)->post(route('dashboard.appointments.cancel', ['uuid' => $appointment->uuid]), ['reason' => 'Truck broke down']);

    $this->actingAs($staff)
        ->get(route('dashboard.appointments.show', ['uuid' => $appointment->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('appointment.status', 'cancelled')
            ->where('appointment.cancelledReason', 'Truck broke down')
            ->where('appointment.history', fn ($history): bool => collect($history)
                ->where('actor', 'Dana Lee')
                ->pluck('description')
                ->contains('Status changed from scheduled to canceled')));
});
