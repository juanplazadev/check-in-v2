<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Enums\RecordHistoryEvent;
use App\Enums\Role;
use App\Enums\UserHistoryEvent;
use App\Models\Appointment;
use App\Models\Building;
use App\Models\Dock;
use App\Models\Location;
use App\Models\User;
use App\Models\UserHistory;
use Inertia\Testing\AssertableInertia;

/*
 * Placing an appointment on one of its location's docks from its dashboard page.
 */

function dockAssigner(Location $location): User
{
    return User::factory()->withRole(Role::Shipping)->worksAt($location)->create();
}

it('places an appointment on a dock and logs it on both trails', function (): void {
    $location = Location::factory()->create();
    $building = Building::factory()->for($location)->create(['name' => 'Building B']);
    $dock = Dock::factory()->for($location)->for($building)->create(['name' => 'Door 12']);
    $appointment = Appointment::factory()->for($location)->create();
    $user = dockAssigner($location);

    $this->actingAs($user)
        ->put(route('dashboard.appointments.dock.update', $appointment->uuid), ['dock' => $dock->uuid])
        ->assertSessionHasNoErrors();

    $appointment->refresh();
    $history = $appointment->history()->where('event', RecordHistoryEvent::DockAssigned)->sole();

    expect($appointment->dock_id)->toBe($dock->id)
        ->and($appointment->dock_assigned_by)->toBe($user->id)
        ->and($appointment->dock_assigned_at)->not->toBeNull()
        ->and($history->context)->toBe(['to' => 'Building B / Door 12'])
        ->and(UserHistory::query()->sole()->event)->toBe(UserHistoryEvent::AppointmentDockAssigned);
});

it('logs where a moved appointment came from', function (): void {
    $location = Location::factory()->create();
    $from = Dock::factory()->for($location)->create(['name' => 'Door 1']);
    $to = Dock::factory()->for($location)->create(['name' => 'Door 2']);
    $appointment = Appointment::factory()->for($location)->create(['dock_id' => $from->id]);

    $this->actingAs(dockAssigner($location))
        ->put(route('dashboard.appointments.dock.update', $appointment->uuid), ['dock' => $to->uuid])
        ->assertSessionHasNoErrors();

    expect($appointment->refresh()->dock_id)->toBe($to->id)
        ->and($appointment->history()->where('event', RecordHistoryEvent::DockAssigned)->sole()->context)
        ->toBe(['from' => 'Door 1', 'to' => 'Door 2']);
});

it('refuses a dock another booking holds at the same time', function (): void {
    $location = Location::factory()->create();
    $dock = Dock::factory()->for($location)->create(['name' => 'Door 1']);
    $at = now()->addDay()->startOfHour();
    Appointment::factory()->for($location)->create(['dock_id' => $dock->id, 'scheduled_for' => $at, 'reference_number' => 'APT11111']);
    $appointment = Appointment::factory()->for($location)->create(['scheduled_for' => $at]);

    $this->actingAs(dockAssigner($location))
        ->put(route('dashboard.appointments.dock.update', $appointment->uuid), ['dock' => $dock->uuid])
        ->assertSessionHasErrors(['dock' => 'APT11111 already holds Door 1 during this appointment.']);

    expect($appointment->refresh()->dock_id)->toBeNull();
});

it('refuses a dock held by a longer booking that is still running when this one starts', function (): void {
    $location = Location::factory()->create();
    $dock = Dock::factory()->for($location)->create(['name' => 'Door 1']);
    $at = now()->addDay()->startOfHour();
    Appointment::factory()->for($location)->scheduledFor($at)->lasting(120)->create(['dock_id' => $dock->id, 'reference_number' => 'APT11111']);
    $appointment = Appointment::factory()->for($location)->scheduledFor($at->copy()->addHour())->create();

    $this->actingAs(dockAssigner($location))
        ->put(route('dashboard.appointments.dock.update', $appointment->uuid), ['dock' => $dock->uuid])
        ->assertSessionHasErrors(['dock' => 'APT11111 already holds Door 1 during this appointment.']);

    expect($appointment->refresh()->dock_id)->toBeNull();
});

it('places a booking on a dock whose previous booking ends as this one starts', function (): void {
    $location = Location::factory()->create();
    $dock = Dock::factory()->for($location)->create();
    $at = now()->addDay()->startOfHour();
    Appointment::factory()->for($location)->scheduledFor($at)->lasting(60)->create(['dock_id' => $dock->id]);
    $appointment = Appointment::factory()->for($location)->scheduledFor($at->copy()->addHour())->create();

    $this->actingAs(dockAssigner($location))
        ->put(route('dashboard.appointments.dock.update', $appointment->uuid), ['dock' => $dock->uuid])
        ->assertSessionHasNoErrors();

    expect($appointment->refresh()->dock_id)->toBe($dock->id);
});

it('lets a dock be reused once the booking holding it is canceled', function (): void {
    $location = Location::factory()->create();
    $dock = Dock::factory()->for($location)->create();
    $at = now()->addDay()->startOfHour();
    Appointment::factory()->for($location)->create(['dock_id' => $dock->id, 'scheduled_for' => $at, 'status' => AppointmentStatus::Cancelled]);
    $appointment = Appointment::factory()->for($location)->create(['scheduled_for' => $at]);

    $this->actingAs(dockAssigner($location))
        ->put(route('dashboard.appointments.dock.update', $appointment->uuid), ['dock' => $dock->uuid])
        ->assertSessionHasNoErrors();

    expect($appointment->refresh()->dock_id)->toBe($dock->id);
});

it("refuses a dock that is not open at the appointment's location", function (string $dockState): void {
    $location = Location::factory()->create();
    $dock = match ($dockState) {
        'elsewhere' => Dock::factory()->create(),
        default => Dock::factory()->for($location)->{$dockState}()->create(),
    };
    $appointment = Appointment::factory()->for($location)->create();

    $this->actingAs(dockAssigner($location))
        ->put(route('dashboard.appointments.dock.update', $appointment->uuid), ['dock' => $dock->uuid])
        ->assertSessionHasErrors(['dock' => "Choose an open dock at this appointment's location."]);

    expect($appointment->refresh()->dock_id)->toBeNull();
})->with([
    'at another location' => 'elsewhere',
    'inactive' => 'inactive',
    'walk-ins only' => 'walkInOnly',
]);

it('refuses to place a canceled appointment', function (): void {
    $location = Location::factory()->create();
    $dock = Dock::factory()->for($location)->create();
    $appointment = Appointment::factory()->for($location)->create(['status' => AppointmentStatus::Cancelled]);

    $this->actingAs(dockAssigner($location))
        ->put(route('dashboard.appointments.dock.update', $appointment->uuid), ['dock' => $dock->uuid])
        ->assertSessionHasErrors(['dock' => 'Only a scheduled or checked-in appointment can be placed on a dock.']);
});

it('takes an appointment off its dock', function (): void {
    $location = Location::factory()->create();
    $dock = Dock::factory()->for($location)->create(['name' => 'Door 1']);
    $appointment = Appointment::factory()->for($location)->create(['dock_id' => $dock->id]);

    $this->actingAs(dockAssigner($location))
        ->delete(route('dashboard.appointments.dock.destroy', $appointment->uuid))
        ->assertSessionHasNoErrors();

    expect($appointment->refresh()->dock_id)->toBeNull()
        ->and($appointment->history()->where('event', RecordHistoryEvent::DockUnassigned)->sole()->context)
        ->toBe(['from' => 'Door 1']);
});

it('forbids staff without assign appointment docks', function (): void {
    $location = Location::factory()->create();
    $dock = Dock::factory()->for($location)->create();
    $appointment = Appointment::factory()->for($location)->create();
    $sales = User::factory()->withRole(Role::Sales)->create();

    $this->actingAs($sales)
        ->put(route('dashboard.appointments.dock.update', $appointment->uuid), ['dock' => $dock->uuid])
        ->assertForbidden();
});

it('hides an appointment at a location the user is not attached to', function (): void {
    $location = Location::factory()->create();
    $dock = Dock::factory()->for($location)->create();
    $appointment = Appointment::factory()->for($location)->create();

    $this->actingAs(dockAssigner(Location::factory()->create()))
        ->put(route('dashboard.appointments.dock.update', $appointment->uuid), ['dock' => $dock->uuid])
        ->assertNotFound();
});

it("offers the location's open docks on the appointment page, marking taken ones", function (): void {
    $location = Location::factory()->create();
    $free = Dock::factory()->for($location)->create(['name' => 'Door 1']);
    $held = Dock::factory()->for($location)->create(['name' => 'Door 2']);
    Dock::factory()->for($location)->inactive()->create(['name' => 'Door 3']);
    $at = now()->addDay()->startOfHour();
    Appointment::factory()->for($location)->create(['dock_id' => $held->id, 'scheduled_for' => $at, 'reference_number' => 'APT22222']);
    $appointment = Appointment::factory()->for($location)->create(['scheduled_for' => $at]);

    $this->actingAs(dockAssigner($location))
        ->get(route('dashboard.appointments.show', $appointment->uuid))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('dockOptions', [
                ['id' => $free->uuid, 'label' => 'Door 1', 'building' => null, 'takenBy' => null],
                ['id' => $held->uuid, 'label' => 'Door 2', 'building' => null, 'takenBy' => 'APT22222'],
            ]));
});
