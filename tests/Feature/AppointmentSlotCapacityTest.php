<?php

declare(strict_types=1);

use App\Actions\BookAppointmentAction;
use App\Appointment\AppointmentScheduleResolver;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Dock;
use App\Models\LoadType;
use App\Models\Location;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

/*
 * Each active dock that takes appointments holds one booking at a time. A
 * booking holds its dock from its start until it ends, so a long load fills
 * every slot it runs through. A location with no such docks takes any number,
 * as before docks existed.
 */

function capacityPayload(Location $location): array
{
    return [
        'datetime' => capacitySlot($location)->format('Y-m-d H:i:s'),
        'po_numbers' => ['SO-00001'],
        'drivers_name' => 'John Doe',
        'drivers_cellphone' => '(201) 555-0123',
    ];
}

/** An hour from now, in the location's own time. */
function capacitySlot(Location $location): CarbonInterface
{
    return now()->addHour()->startOfHour()->setTimezone($location->timezone);
}

function bookSlot(Location $location, AppointmentStatus $status = AppointmentStatus::Scheduled): Appointment
{
    return Appointment::factory()->for($location)->create([
        'scheduled_for' => capacitySlot($location)->utc(),
        'status' => $status,
    ]);
}

/** A booking at the location that starts $offset minutes after capacitySlot() and lasts $minutes. */
function bookRange(Location $location, int $offset, int $minutes): Appointment
{
    return Appointment::factory()
        ->for($location)
        ->scheduledFor(capacitySlot($location)->addMinutes($offset)->utc())
        ->lasting($minutes)
        ->create();
}

/** A driver booking a load type $offset minutes after capacitySlot(). */
function loadTypePayload(Location $location, LoadType $loadType, int $offset = 0): array
{
    return [
        ...capacityPayload($location),
        'datetime' => capacitySlot($location)->addMinutes($offset)->format('Y-m-d H:i:s'),
        'load_type' => $loadType->uuid,
    ];
}

beforeEach(function (): void {
    Date::setTestNow(Date::today('UTC')->setTime(8, 0));
});

it('rejects a booking when every dock is taken at that time', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    Dock::factory()->for($location)->create();
    bookSlot($location);

    post(route('appointment.gate', $location->uuid), ['uuid' => $location->uuid]);

    post(route('appointment.store', $location->uuid), capacityPayload($location))
        ->assertSessionHasErrors(['datetime' => 'That time just filled up. Please choose another time.']);

    expect(Appointment::query()->count())->toBe(1);
});

it('takes one booking per bookable dock', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    Dock::factory()->for($location)->count(2)->create();
    bookSlot($location);

    post(route('appointment.gate', $location->uuid), ['uuid' => $location->uuid]);

    post(route('appointment.store', $location->uuid), capacityPayload($location))
        ->assertSessionHasNoErrors();

    expect(Appointment::query()->count())->toBe(2);
});

it('takes any number of bookings at a location with no bookable docks', function (string $dockState): void {
    $location = Location::factory()->appointmentsOnly()->create();

    if ($dockState !== 'none') {
        Dock::factory()->for($location)->{$dockState}()->create();
    }

    bookSlot($location);
    bookSlot($location);

    post(route('appointment.gate', $location->uuid), ['uuid' => $location->uuid]);

    post(route('appointment.store', $location->uuid), capacityPayload($location))
        ->assertSessionHasNoErrors();

    expect(Appointment::query()->count())->toBe(3);
})->with([
    'no docks' => 'none',
    'only an inactive dock' => 'inactive',
    'only a walk-in dock' => 'walkInOnly',
]);

it('frees the slot when the booking holding it is canceled or a no-show', function (AppointmentStatus $status): void {
    $location = Location::factory()->appointmentsOnly()->create();
    Dock::factory()->for($location)->create();
    bookSlot($location, $status);

    post(route('appointment.gate', $location->uuid), ['uuid' => $location->uuid]);

    post(route('appointment.store', $location->uuid), capacityPayload($location))
        ->assertSessionHasNoErrors();
})->with([
    'canceled' => AppointmentStatus::Cancelled,
    'no-show' => AppointmentStatus::NoShow,
]);

it('frees the slot when the booking holding it is archived', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    Dock::factory()->for($location)->create();
    bookSlot($location)->delete();

    post(route('appointment.gate', $location->uuid), ['uuid' => $location->uuid]);

    post(route('appointment.store', $location->uuid), capacityPayload($location))
        ->assertSessionHasNoErrors();
});

it('rechecks the slot under the lock when booking', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    Dock::factory()->for($location)->create();
    bookSlot($location);

    $locationDTO = resolve(AppointmentScheduleResolver::class)->buildDTO($location->load(['address', 'appointmentSchedule', 'appointmentScheduleOverrides']), now());

    $booking = [...capacityPayload($location), 'load_type_id' => null, 'duration_minutes' => 30];

    expect(fn (): Appointment => resolve(BookAppointmentAction::class)->handle($booking, $locationDTO))
        ->toThrow(ValidationException::class, 'That time just filled up. Please choose another time.');

    expect(Appointment::query()->count())->toBe(1);
});

it('rejects a booking that starts while a longer load still holds the only dock', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    Dock::factory()->for($location)->create();
    $loadType = LoadType::factory()->for($location)->lasting(30)->create();
    bookRange($location, 0, 90);

    post(route('appointment.gate', $location->uuid), ['uuid' => $location->uuid]);

    post(route('appointment.store', $location->uuid), loadTypePayload($location, $loadType, 60))
        ->assertSessionHasErrors(['datetime' => 'That time just filled up. Please choose another time.']);

    expect(Appointment::query()->count())->toBe(1);
});

it('rejects a long load that would run into a later booking on the only dock', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    Dock::factory()->for($location)->create();
    $loadType = LoadType::factory()->for($location)->lasting(90)->create();
    bookRange($location, 60, 30);

    post(route('appointment.gate', $location->uuid), ['uuid' => $location->uuid]);

    post(route('appointment.store', $location->uuid), loadTypePayload($location, $loadType))
        ->assertSessionHasErrors(['datetime' => 'That time just filled up. Please choose another time.']);
});

it('takes a booking that starts as the previous one ends', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    Dock::factory()->for($location)->create();
    $loadType = LoadType::factory()->for($location)->lasting(60)->create();
    bookRange($location, 0, 60);

    post(route('appointment.gate', $location->uuid), ['uuid' => $location->uuid]);

    post(route('appointment.store', $location->uuid), loadTypePayload($location, $loadType, 60))
        ->assertSessionHasNoErrors();

    expect(Appointment::query()->latest('id')->first()->ends_at->equalTo(capacitySlot($location)->addMinutes(120)))->toBeTrue();
});

it('counts only the bookings running at the same moment, not every booking the load overlaps', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    Dock::factory()->for($location)->count(2)->create();
    $loadType = LoadType::factory()->for($location)->lasting(120)->create();
    // One dock is busy early and again late, but never twice at once, so the other dock stays free throughout.
    bookRange($location, 0, 30);
    bookRange($location, 60, 30);

    post(route('appointment.gate', $location->uuid), ['uuid' => $location->uuid]);

    post(route('appointment.store', $location->uuid), loadTypePayload($location, $loadType))
        ->assertSessionHasNoErrors();

    expect(Appointment::query()->count())->toBe(3);
});

it('lists the full start times on the booking form for each load type length', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    Dock::factory()->for($location)->create();
    LoadType::factory()->for($location)->lasting(15)->create();
    LoadType::factory()->for($location)->lasting(45)->create();
    bookRange($location, 60, 30);
    $slot = capacitySlot($location);
    $date = $slot->toDateString();

    post(route('appointment.gate', $location->uuid), ['uuid' => $location->uuid]);

    get(route('appointment.form', $location->uuid))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('AppointmentForm')
            ->where('fullSlots.15', [$date => ['10:00:00', '10:15:00']])
            ->where('fullSlots.45', [$date => ['09:30:00', '09:45:00', '10:00:00', '10:15:00']]));
});
