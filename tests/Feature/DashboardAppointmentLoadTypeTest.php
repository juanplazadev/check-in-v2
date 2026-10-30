<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Enums\RecordHistoryEvent;
use App\Enums\Role;
use App\Enums\UserHistoryEvent;
use App\Models\Appointment;
use App\Models\Dock;
use App\Models\LoadType;
use App\Models\Location;
use App\Models\User;
use App\Models\UserHistory;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia;

/*
 * Staff changing an appointment's load type from its dashboard page, which
 * changes how long the booking holds a dock.
 */

function loadTypeChanger(Location $location): User
{
    return User::factory()->withRole(Role::Shipping)->worksAt($location)->create();
}

it('changes the load type, moves the end time and logs it on both trails', function (): void {
    $location = Location::factory()->create();
    Dock::factory()->for($location)->create();
    $short = LoadType::factory()->for($location)->lasting(60)->create(['name' => 'Live load']);
    $long = LoadType::factory()->for($location)->lasting(180)->create(['name' => 'Floor unload']);
    $at = now()->addDay()->startOfHour();
    $appointment = Appointment::factory()->scheduledFor($at)->ofLoadType($short)->create();

    $this->actingAs(loadTypeChanger($location))
        ->put(route('dashboard.appointments.loadType.update', $appointment->uuid), ['load_type' => $long->uuid])
        ->assertSessionHasNoErrors();

    $appointment->refresh();
    $change = ['load_type' => ['from' => 'Live load', 'to' => 'Floor unload']];

    expect($appointment->load_type_id)->toBe($long->id)
        ->and($appointment->ends_at->equalTo($at->copy()->addMinutes(180)))->toBeTrue()
        ->and($appointment->history()->where('event', RecordHistoryEvent::LoadTypeChanged)->sole()->context)->toBe($change)
        ->and(UserHistory::query()->sole())
        ->event->toBe(UserHistoryEvent::AppointmentLoadTypeChanged);
});

it('lets the only booking on the location grow without counting itself', function (): void {
    $location = Location::factory()->create();
    $dock = Dock::factory()->for($location)->create();
    $short = LoadType::factory()->for($location)->lasting(60)->create();
    $long = LoadType::factory()->for($location)->lasting(180)->create();
    $appointment = Appointment::factory()->scheduledFor(now()->addDay()->startOfHour())->ofLoadType($short)->create(['dock_id' => $dock->id]);

    $this->actingAs(loadTypeChanger($location))
        ->put(route('dashboard.appointments.loadType.update', $appointment->uuid), ['load_type' => $long->uuid])
        ->assertSessionHasNoErrors();

    expect($appointment->refresh()->load_type_id)->toBe($long->id);
});

it('refuses a longer load when the location has no dock free for it', function (): void {
    $location = Location::factory()->create();
    Dock::factory()->for($location)->create();
    $short = LoadType::factory()->for($location)->lasting(60)->create();
    $long = LoadType::factory()->for($location)->lasting(180)->create();
    $at = now()->addDay()->startOfHour();
    $appointment = Appointment::factory()->scheduledFor($at)->ofLoadType($short)->create();
    Appointment::factory()->for($location)->scheduledFor($at->copy()->addHours(2))->create();

    $this->actingAs(loadTypeChanger($location))
        ->put(route('dashboard.appointments.loadType.update', $appointment->uuid), ['load_type' => $long->uuid])
        ->assertSessionHasErrors(['load_type' => 'Another booking needs a dock during that time. There is no room for a longer load.']);

    expect($appointment->refresh()->load_type_id)->toBe($short->id);
});

it('refuses a load that would run past closing time', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    Dock::factory()->for($location)->create();
    $short = LoadType::factory()->for($location)->lasting(60)->create();
    $long = LoadType::factory()->for($location)->lasting(180)->create();
    // The factory's schedule closes at 23:59:59, so a 3-hour load from 22:00 runs past it.
    $at = Date::now($location->timezone)->addDay()->setTime(22, 0);
    $appointment = Appointment::factory()->scheduledFor($at)->ofLoadType($short)->create();

    $this->actingAs(loadTypeChanger($location))
        ->put(route('dashboard.appointments.loadType.update', $appointment->uuid), ['load_type' => $long->uuid])
        ->assertSessionHasErrors(['load_type' => 'This load would run past closing time.']);

    expect($appointment->refresh()->load_type_id)->toBe($short->id);
});

it('refuses a longer load when its dock is taken in the new time', function (): void {
    $location = Location::factory()->create();
    $dock = Dock::factory()->for($location)->create(['name' => 'Door 1']);
    Dock::factory()->for($location)->create();
    $short = LoadType::factory()->for($location)->lasting(60)->create();
    $long = LoadType::factory()->for($location)->lasting(180)->create();
    $at = now()->addDay()->startOfHour();
    $appointment = Appointment::factory()->scheduledFor($at)->ofLoadType($short)->create(['dock_id' => $dock->id]);
    Appointment::factory()->for($location)->scheduledFor($at->copy()->addHours(2))->create(['dock_id' => $dock->id, 'reference_number' => 'APT11111']);

    $this->actingAs(loadTypeChanger($location))
        ->put(route('dashboard.appointments.loadType.update', $appointment->uuid), ['load_type' => $long->uuid])
        ->assertSessionHasErrors(['load_type' => 'APT11111 already holds Door 1 during the new time.']);

    expect($appointment->refresh()->load_type_id)->toBe($short->id);
});

it('refuses a load type that is not open at the appointment\'s location', function (string $loadTypeState): void {
    $location = Location::factory()->create();
    Dock::factory()->for($location)->create();
    $appointment = Appointment::factory()->for($location)->scheduledFor(now()->addDay()->startOfHour())->create();
    $loadType = match ($loadTypeState) {
        'elsewhere' => LoadType::factory()->create(),
        default => LoadType::factory()->for($location)->inactive()->create(),
    };

    $this->actingAs(loadTypeChanger($location))
        ->put(route('dashboard.appointments.loadType.update', $appointment->uuid), ['load_type' => $loadType->uuid])
        ->assertSessionHasErrors(['load_type' => "Choose an active load type at this appointment's location."]);

    expect($appointment->refresh()->load_type_id)->toBeNull();
})->with([
    'at another location' => 'elsewhere',
    'inactive' => 'inactive',
]);

it('refuses to change a canceled appointment', function (): void {
    $location = Location::factory()->create();
    $loadType = LoadType::factory()->for($location)->create();
    $appointment = Appointment::factory()->for($location)->create(['status' => AppointmentStatus::Cancelled]);

    $this->actingAs(loadTypeChanger($location))
        ->put(route('dashboard.appointments.loadType.update', $appointment->uuid), ['load_type' => $loadType->uuid])
        ->assertSessionHasErrors(['load_type' => "Only a scheduled or checked-in appointment's load type can change."]);
});

it('forbids staff without change appointment load types', function (): void {
    $location = Location::factory()->create();
    $loadType = LoadType::factory()->for($location)->create();
    $appointment = Appointment::factory()->for($location)->create();

    $this->actingAs(User::factory()->withRole(Role::Sales)->create())
        ->put(route('dashboard.appointments.loadType.update', $appointment->uuid), ['load_type' => $loadType->uuid])
        ->assertForbidden();
});

it('hides an appointment at a location the user is not attached to', function (): void {
    $location = Location::factory()->create();
    $loadType = LoadType::factory()->for($location)->create();
    $appointment = Appointment::factory()->for($location)->create();

    $this->actingAs(loadTypeChanger(Location::factory()->create()))
        ->put(route('dashboard.appointments.loadType.update', $appointment->uuid), ['load_type' => $loadType->uuid])
        ->assertNotFound();
});

it("offers the location's active load types on the appointment page", function (): void {
    $location = Location::factory()->create();
    Dock::factory()->for($location)->create();
    $live = LoadType::factory()->for($location)->lasting(90)->create(['name' => 'Live load', 'sort_order' => 1]);
    LoadType::factory()->for($location)->inactive()->create(['sort_order' => 2]);
    $appointment = Appointment::factory()->scheduledFor(now()->addDay()->startOfHour())->ofLoadType($live)->create();

    $this->actingAs(loadTypeChanger($location))
        ->get(route('dashboard.appointments.show', $appointment->uuid))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('appointment.loadType', ['uuid' => $live->uuid, 'name' => 'Live load'])
            ->where('loadTypeOptions', [
                ['id' => $live->uuid, 'name' => 'Live load', 'durationMinutes' => 90],
            ]));
});

it('refuses a change at a location with no bookable docks', function (): void {
    $location = Location::factory()->create();
    $short = LoadType::factory()->for($location)->lasting(60)->create();
    $long = LoadType::factory()->for($location)->lasting(180)->create();
    $appointment = Appointment::factory()->scheduledFor(now()->addDay()->startOfHour())->ofLoadType($short)->create();

    $this->actingAs(loadTypeChanger($location))
        ->put(route('dashboard.appointments.loadType.update', $appointment->uuid), ['load_type' => $long->uuid])
        ->assertSessionHasErrors(['load_type' => 'Load types are off until this location has docks that take appointments.']);

    expect($appointment->refresh()->load_type_id)->toBe($short->id);
});

it('offers no picker at a location with no bookable docks', function (): void {
    $location = Location::factory()->create();
    $live = LoadType::factory()->for($location)->create(['name' => 'Live load']);
    $appointment = Appointment::factory()->scheduledFor(now()->addDay()->startOfHour())->ofLoadType($live)->create();

    $this->actingAs(loadTypeChanger($location))
        ->get(route('dashboard.appointments.show', $appointment->uuid))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('appointment.loadType', ['uuid' => $live->uuid, 'name' => 'Live load'])
            ->where('loadTypeOptions', null));
});
