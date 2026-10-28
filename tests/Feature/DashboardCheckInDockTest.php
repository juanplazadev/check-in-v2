<?php

declare(strict_types=1);

use App\Enums\RecordHistoryEvent;
use App\Enums\Role;
use App\Enums\UserHistoryEvent;
use App\Models\Appointment;
use App\Models\Building;
use App\Models\CheckIn;
use App\Models\Dock;
use App\Models\Location;
use App\Models\User;
use App\Models\UserHistory;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\DoorAssignment;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

/*
 * Sending a waiting truck to a door from the dashboard, and texting the driver where to go.
 */

function doorAssigner(Location $location): User
{
    return User::factory()->withRole(Role::Shipping)->worksAt($location)->create();
}

function waitingAt(Location $location, array $attributes = []): CheckIn
{
    return CheckIn::factory()->atLocation($location)->pending()->create($attributes);
}

beforeEach(function (): void {
    Notification::fake();
});

it('sends a truck to a door, logs it on both trails and texts the driver', function (): void {
    $location = Location::factory()->create();
    $dock = Dock::factory()->for($location)->for(Building::factory()->for($location)->state(['name' => 'Building B']))->create(['name' => 'Door 12']);
    $checkIn = waitingAt($location, ['drivers_cellphone' => '+12015550123', 'locale' => 'es']);
    $user = doorAssigner($location);

    $this->actingAs($user)
        ->put(route('dashboard.checkIns.dock.update', $checkIn->uuid), ['dock' => $dock->uuid, 'notify' => true])
        ->assertSessionHasNoErrors();

    $checkIn->refresh();

    expect($checkIn->dock_id)->toBe($dock->id)
        ->and($checkIn->dock_assigned_by)->toBe($user->id)
        ->and($checkIn->door_notified_at)->not->toBeNull()
        ->and($checkIn->history()->where('event', RecordHistoryEvent::DockAssigned)->sole()->context)->toBe(['to' => 'Building B / Door 12'])
        ->and(UserHistory::query()->sole()->event)->toBe(UserHistoryEvent::CheckInDockAssigned);

    Notification::assertSentOnDemand(
        DoorAssignment::class,
        fn (DoorAssignment $notification, array $channels, object $notifiable): bool => $channels === [SmsChannel::class]
            && $notifiable->routes['sms'] === '+12015550123'
            && $notification->locale === 'es',
    );
});

it('texts a driver with an account at the number on the check-in', function (): void {
    $location = Location::factory()->create();
    $dock = Dock::factory()->for($location)->create();
    $driver = User::factory()->withRole(Role::Driver)->create(['cellphone' => '+12015559999']);
    $checkIn = waitingAt($location, ['user_id' => $driver->id, 'drivers_cellphone' => '+12015550123']);

    $this->actingAs(doorAssigner($location))
        ->put(route('dashboard.checkIns.dock.update', $checkIn->uuid), ['dock' => $dock->uuid, 'notify' => true]);

    Notification::assertSentOnDemand(
        DoorAssignment::class,
        fn (DoorAssignment $notification, array $channels, object $notifiable): bool => $notifiable->routes['sms'] === '+12015550123',
    );
    Notification::assertNotSentTo($driver, DoorAssignment::class);
});

it('does not text the driver when staff untick it', function (): void {
    $location = Location::factory()->create();
    $dock = Dock::factory()->for($location)->create();
    $checkIn = waitingAt($location);

    $this->actingAs(doorAssigner($location))
        ->put(route('dashboard.checkIns.dock.update', $checkIn->uuid), ['dock' => $dock->uuid, 'notify' => false])
        ->assertSessionHasNoErrors();

    expect($checkIn->refresh()->dock_id)->toBe($dock->id)
        ->and($checkIn->door_notified_at)->toBeNull();
    Notification::assertNothingSent();
});

it('does not text the driver again when the door did not change', function (): void {
    $location = Location::factory()->create();
    $dock = Dock::factory()->for($location)->create();
    $checkIn = waitingAt($location, ['dock_id' => $dock->id]);

    $this->actingAs(doorAssigner($location))
        ->put(route('dashboard.checkIns.dock.update', $checkIn->uuid), ['dock' => $dock->uuid, 'notify' => true])
        ->assertSessionHasNoErrors();

    Notification::assertNothingSent();
    expect($checkIn->history()->where('event', RecordHistoryEvent::DockAssigned)->count())->toBe(0);
});

it('names the building in the text only when the door has one', function (?string $building, string $locale, string $expected): void {
    $location = Location::factory()->create();
    $dock = Dock::factory()->for($location)->create([
        'name' => 'Door 12',
        'building_id' => $building === null ? null : Building::factory()->for($location)->create(['name' => $building])->id,
    ]);
    $checkIn = waitingAt($location, ['reference_number' => 'C1234567']);

    app()->setLocale($locale);
    $body = new DoorAssignment($checkIn, $dock->load('building'))
        ->toSms(Notification::route('sms', '+12015550123'))
        ->body;

    expect($body)->toBe(sprintf($expected, config('app.name')));
})->with([
    'english with building' => ['Building B', 'en', '%s: Ref C1234567. Please go to Door 12, Building B.'],
    'english without building' => [null, 'en', '%s: Ref C1234567. Please go to Door 12.'],
    'spanish with building' => ['Building B', 'es', '%s: Ref C1234567. Por favor diríjase a Door 12, Building B.'],
]);

it('refuses a door another waiting truck is using', function (): void {
    $location = Location::factory()->create();
    $dock = Dock::factory()->for($location)->create(['name' => 'Door 12']);
    waitingAt($location, ['dock_id' => $dock->id, 'reference_number' => 'C1111111']);
    $checkIn = waitingAt($location);

    $this->actingAs(doorAssigner($location))
        ->put(route('dashboard.checkIns.dock.update', $checkIn->uuid), ['dock' => $dock->uuid, 'notify' => true])
        ->assertSessionHasErrors(['dock' => 'Door 12 is in use by C1111111.']);

    expect($checkIn->refresh()->dock_id)->toBeNull();
    Notification::assertNothingSent();
});

it('frees a door once the truck using it is done', function (string $state): void {
    $location = Location::factory()->create();
    $dock = Dock::factory()->for($location)->create();
    CheckIn::factory()->atLocation($location)->{$state}()->create(['dock_id' => $dock->id]);
    $checkIn = waitingAt($location);

    $this->actingAs(doorAssigner($location))
        ->put(route('dashboard.checkIns.dock.update', $checkIn->uuid), ['dock' => $dock->uuid, 'notify' => false])
        ->assertSessionHasNoErrors();

    expect($checkIn->refresh()->dock_id)->toBe($dock->id);
})->with(['completed', 'cancelled']);

it("refuses a door that is not active at the check-in's location", function (string $dockState): void {
    $location = Location::factory()->create();
    $dock = $dockState === 'elsewhere'
        ? Dock::factory()->create()
        : Dock::factory()->for($location)->inactive()->create();
    $checkIn = waitingAt($location);

    $this->actingAs(doorAssigner($location))
        ->put(route('dashboard.checkIns.dock.update', $checkIn->uuid), ['dock' => $dock->uuid, 'notify' => true])
        ->assertSessionHasErrors(['dock' => "Choose an active door at this check-in's location."]);
})->with(['at another location' => 'elsewhere', 'inactive' => 'inactive']);

it('accepts a walk-in-only door', function (): void {
    $location = Location::factory()->create();
    $dock = Dock::factory()->for($location)->walkInOnly()->create();
    $checkIn = waitingAt($location);

    $this->actingAs(doorAssigner($location))
        ->put(route('dashboard.checkIns.dock.update', $checkIn->uuid), ['dock' => $dock->uuid, 'notify' => false])
        ->assertSessionHasNoErrors();

    expect($checkIn->refresh()->dock_id)->toBe($dock->id);
});

it('refuses a check-in that is no longer waiting', function (string $state): void {
    $location = Location::factory()->create();
    $dock = Dock::factory()->for($location)->create();
    $checkIn = CheckIn::factory()->atLocation($location)->{$state}()->create();

    $this->actingAs(doorAssigner($location))
        ->put(route('dashboard.checkIns.dock.update', $checkIn->uuid), ['dock' => $dock->uuid, 'notify' => true])
        ->assertSessionHasErrors(['dock' => 'Only a pending check-in can be sent to a door.']);
})->with(['completed', 'cancelled']);

it('resends the door text', function (): void {
    $location = Location::factory()->create();
    $dock = Dock::factory()->for($location)->create();
    $checkIn = waitingAt($location, ['dock_id' => $dock->id]);

    $this->actingAs(doorAssigner($location))
        ->post(route('dashboard.checkIns.dock.notify', $checkIn->uuid))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', 'Door texted to the driver.');

    Notification::assertSentOnDemandTimes(DoorAssignment::class, 1);
    expect($checkIn->refresh()->door_notified_at)->not->toBeNull();
});

it('refuses to text a driver who has no door', function (): void {
    $location = Location::factory()->create();
    $checkIn = waitingAt($location);

    $this->actingAs(doorAssigner($location))
        ->post(route('dashboard.checkIns.dock.notify', $checkIn->uuid))
        ->assertSessionHasErrors(['dock' => 'Only a waiting truck with a door can be texted its door.']);

    Notification::assertNothingSent();
});

it('takes a truck off its door without texting the driver', function (): void {
    $location = Location::factory()->create();
    $dock = Dock::factory()->for($location)->create(['name' => 'Door 1']);
    $checkIn = waitingAt($location, ['dock_id' => $dock->id]);

    $this->actingAs(doorAssigner($location))
        ->delete(route('dashboard.checkIns.dock.destroy', $checkIn->uuid))
        ->assertSessionHasNoErrors();

    expect($checkIn->refresh()->dock_id)->toBeNull()
        ->and($checkIn->history()->where('event', RecordHistoryEvent::DockUnassigned)->sole()->context)->toBe(['from' => 'Door 1']);
    Notification::assertNothingSent();
});

it('forbids staff without assign check-in docks', function (): void {
    $location = Location::factory()->create();
    $dock = Dock::factory()->for($location)->create();
    $checkIn = waitingAt($location);

    $this->actingAs(User::factory()->withRole(Role::Sales)->create())
        ->put(route('dashboard.checkIns.dock.update', $checkIn->uuid), ['dock' => $dock->uuid, 'notify' => true])
        ->assertForbidden();
});

it('hides a check-in at a location the user is not attached to', function (): void {
    $location = Location::factory()->create();
    $dock = Dock::factory()->for($location)->create();
    $checkIn = waitingAt($location);

    $this->actingAs(doorAssigner(Location::factory()->create()))
        ->put(route('dashboard.checkIns.dock.update', $checkIn->uuid), ['dock' => $dock->uuid, 'notify' => true])
        ->assertNotFound();
});

it("offers the location's active doors on the check-in page, with the booked door", function (): void {
    $location = Location::factory()->create();
    $booked = Dock::factory()->for($location)->create(['name' => 'Door 1']);
    $busy = Dock::factory()->for($location)->create(['name' => 'Door 2']);
    Dock::factory()->for($location)->inactive()->create(['name' => 'Door 3']);
    waitingAt($location, ['dock_id' => $busy->id, 'reference_number' => 'C2222222']);
    $appointment = Appointment::factory()->for($location)->create(['dock_id' => $booked->id]);
    $checkIn = CheckIn::factory()->atLocation($location)->pending()->linkedToAppointment($appointment)->create();

    $this->actingAs(doorAssigner($location))
        ->get(route('dashboard.checkIns.show', $checkIn->uuid))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('checkIn.appointmentDockUuid', $booked->uuid)
            ->where('doorOptions', [
                ['id' => $booked->uuid, 'label' => 'Door 1', 'building' => null, 'inUseBy' => null],
                ['id' => $busy->uuid, 'label' => 'Door 2', 'building' => null, 'inUseBy' => 'C2222222'],
            ]));
});

it('loads door options on the live queue only when asked', function (): void {
    $location = Location::factory()->create();
    $dock = Dock::factory()->for($location)->create(['name' => 'Door 1']);
    Dock::factory()->for(Location::factory())->create();
    $user = doorAssigner($location);

    $this->actingAs($user)
        ->get(route('dashboard.queue'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->missing('doorOptions')
            ->reloadOnly('doorOptions', fn (AssertableInertia $reload): AssertableInertia => $reload
                ->where('doorOptions', [
                    $location->uuid => [['id' => $dock->uuid, 'label' => 'Door 1', 'building' => null, 'inUseBy' => null]],
                ])));
});
