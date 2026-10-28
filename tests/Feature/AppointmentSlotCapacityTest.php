<?php

declare(strict_types=1);

use Carbon\CarbonInterface;
use App\Actions\BookAppointmentAction;
use App\Appointment\AppointmentScheduleResolver;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Dock;
use App\Models\Location;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

/*
 * Each active dock that takes appointments lets one booking into every slot.
 * A location with no such docks takes any number, as before docks existed.
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

    expect(fn (): Appointment => resolve(BookAppointmentAction::class)->handle(capacityPayload($location), $locationDTO))
        ->toThrow(ValidationException::class, 'That time just filled up. Please choose another time.');

    expect(Appointment::query()->count())->toBe(1);
});

it('lists the full slots on the booking form by local date', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    Dock::factory()->for($location)->create();
    bookSlot($location);
    $slot = capacitySlot($location);

    post(route('appointment.gate', $location->uuid), ['uuid' => $location->uuid]);

    get(route('appointment.form', $location->uuid))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('AppointmentForm')
            ->where('fullSlots', [$slot->toDateString() => [$slot->format('H:i:s')]]));
});
