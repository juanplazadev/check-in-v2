<?php

declare(strict_types=1);

use App\Enums\RecordHistoryEvent;
use App\Enums\Role;
use App\Enums\UserHistoryEvent;
use App\Models\Appointment;
use App\Models\AppointmentScheduleOverride;
use App\Models\Location;
use App\Models\RecordHistory;
use App\Models\User;
use App\Models\UserHistory;
use App\Schedule\WeeklyScheduleResolver;
use Inertia\Testing\AssertableInertia;

/*
 * Editing a location's weekly check-in and appointment hours from its hours pages.
 */

function hoursManager(Location $location): User
{
    return User::factory()->withRole(Role::ShippingManager)->worksAt($location)->create();
}

/**
 * Every day open 08:00-17:00, with the given days replaced. Keyed by Carbon's day number.
 *
 * @param  array<int, array{is_open: bool, open_time: string|null, close_time: string|null}>  $overrides
 * @return array<int, array{is_open: bool, open_time: string|null, close_time: string|null}>
 */
function hoursWeek(array $overrides = []): array
{
    $week = array_fill(0, 7, ['is_open' => true, 'open_time' => '08:00', 'close_time' => '17:00']);

    return array_replace($week, $overrides);
}

function closedDay(): array
{
    return ['is_open' => false, 'open_time' => null, 'close_time' => null];
}

dataset('schedule features', [
    'check-in hours' => ['dashboard.locations.checkInHours', 'checkinSchedule', RecordHistoryEvent::CheckInHoursEdited, UserHistoryEvent::LocationCheckInHoursEdited, 'Check-in'],
    'appointment hours' => ['dashboard.locations.appointmentHours', 'appointmentSchedule', RecordHistoryEvent::AppointmentHoursEdited, UserHistoryEvent::LocationAppointmentHoursEdited, 'Appointment'],
]);

it('saves the week and logs each changed day on both trails', function (string $route, string $relation, RecordHistoryEvent $event, UserHistoryEvent $userEvent, string $label): void {
    // Both features start open around the clock every day.
    $location = Location::factory()->create(['name' => 'Alpha Dock', 'is_appointments_enabled' => true]);
    $manager = hoursManager($location);

    $this->actingAs($manager)
        ->from(route($route, ['uuid' => $location->uuid]))
        ->put(route($route.'.update', ['uuid' => $location->uuid]), [
            'days' => hoursWeek([
                0 => closedDay(),
                5 => ['is_open' => true, 'open_time' => '07:30', 'close_time' => '15:00'],
                6 => ['is_open' => true, 'open_time' => '00:00', 'close_time' => '23:59'],
            ]),
        ])
        ->assertRedirect(route($route, ['uuid' => $location->uuid]))
        ->assertInertiaFlash('toast.message', $label.' hours for Alpha Dock saved.');

    $rows = $location->{$relation}()->orderBy('day_of_week')->get()
        ->mapWithKeys(fn ($row): array => [$row->day_of_week => mb_substr($row->open_time, 0, 5).'-'.mb_substr($row->close_time, 0, 8)])
        ->all();

    $history = $location->history()->sole();
    $trail = UserHistory::query()->sole();

    expect($rows)->toBe([
        1 => '08:00-17:00:00',
        2 => '08:00-17:00:00',
        3 => '08:00-17:00:00',
        4 => '08:00-17:00:00',
        5 => '07:30-15:00:00',
        6 => '00:00-23:59:59',
    ])
        ->and($history->event)->toBe($event)
        ->and($history->user_id)->toBe($manager->id)
        // Saturday was already open around the clock, so it is not logged.
        ->and($history->context)->toBe([
            'monday' => ['from' => 'Open 24 hours', 'to' => '8:00 AM - 5:00 PM'],
            'tuesday' => ['from' => 'Open 24 hours', 'to' => '8:00 AM - 5:00 PM'],
            'wednesday' => ['from' => 'Open 24 hours', 'to' => '8:00 AM - 5:00 PM'],
            'thursday' => ['from' => 'Open 24 hours', 'to' => '8:00 AM - 5:00 PM'],
            'friday' => ['from' => 'Open 24 hours', 'to' => '7:30 AM - 3:00 PM'],
            'sunday' => ['from' => 'Open 24 hours', 'to' => 'Closed'],
        ])
        ->and($trail->event)->toBe($userEvent)
        ->and($trail->changes['location'])->toBe($location->uuid)
        ->and($trail->changes['sunday'])->toBe(['from' => 'Open 24 hours', 'to' => 'Closed']);
})->with('schedule features');

it('leaves the other feature hours alone', function (): void {
    $location = Location::factory()->create(['is_appointments_enabled' => true]);

    $this->actingAs(hoursManager($location))
        ->put(route('dashboard.locations.checkInHours.update', ['uuid' => $location->uuid]), ['days' => hoursWeek()])
        ->assertSessionHasNoErrors();

    expect($location->appointmentSchedule()->where('open_time', '00:00:00')->count())->toBe(7);
});

it('writes nothing when no day changed', function (string $route): void {
    $location = Location::factory()->create(['is_appointments_enabled' => true]);
    $allDay = array_fill(0, 7, ['is_open' => true, 'open_time' => '00:00', 'close_time' => '23:59']);

    $this->actingAs(hoursManager($location))
        ->put(route($route.'.update', ['uuid' => $location->uuid]), ['days' => $allDay])
        ->assertInertiaFlash('toast.message', 'No changes to save.');

    expect(RecordHistory::query()->count())->toBe(0)
        ->and(UserHistory::query()->count())->toBe(0);
})->with([
    'check-in hours' => ['dashboard.locations.checkInHours'],
    'appointment hours' => ['dashboard.locations.appointmentHours'],
]);

it('shows new hours on the public schedule right away', function (): void {
    $this->travelTo('2026-12-02 14:00:00');
    $location = Location::factory()->create(['timezone' => 'UTC']);
    $schedule = resolve(WeeklyScheduleResolver::class);

    // Warm the cache with the old, around-the-clock hours.
    $schedule->getWeeks();

    $this->actingAs(hoursManager($location))
        ->put(route('dashboard.locations.checkInHours.update', ['uuid' => $location->uuid]), [
            'days' => hoursWeek([1 => closedDay()]),
        ])
        ->assertSessionHasNoErrors();

    $week = $schedule->getWeeks()->firstWhere('id', $location->uuid);

    expect($week->checkInWeek[0]->hours)->toBeNull()
        ->and($week->checkInWeek[1]->hours)->toBe('8:00 AM – 5:00 PM');
});

it('refuses a day that closes before it opens', function (): void {
    $location = Location::factory()->create();

    $this->actingAs(hoursManager($location))
        ->put(route('dashboard.locations.checkInHours.update', ['uuid' => $location->uuid]), [
            'days' => hoursWeek([3 => ['is_open' => true, 'open_time' => '17:00', 'close_time' => '08:00']]),
        ])
        ->assertSessionHasErrors(['days.3.close_time' => 'Must be after the opening time.']);

    expect($location->checkinSchedule()->where('open_time', '00:00:00')->count())->toBe(7);
});

it('requires both times on an open day', function (): void {
    $location = Location::factory()->create();

    $this->actingAs(hoursManager($location))
        ->put(route('dashboard.locations.checkInHours.update', ['uuid' => $location->uuid]), [
            'days' => hoursWeek([2 => ['is_open' => true, 'open_time' => null, 'close_time' => null]]),
        ])
        ->assertSessionHasErrors([
            'days.2.open_time' => 'Choose an opening time.',
            'days.2.close_time' => 'Choose a closing time.',
        ]);
});

it('requires all seven days', function (): void {
    $location = Location::factory()->create();
    $week = hoursWeek();
    unset($week[0]);

    $this->actingAs(hoursManager($location))
        ->put(route('dashboard.locations.checkInHours.update', ['uuid' => $location->uuid]), ['days' => $week])
        ->assertSessionHasErrors('days');

    expect($location->checkinSchedule()->count())->toBe(7);
});

it('rejects fields outside the hours', function (): void {
    $location = Location::factory()->create();

    $this->actingAs(hoursManager($location))
        ->put(route('dashboard.locations.checkInHours.update', ['uuid' => $location->uuid]), [
            'days' => hoursWeek(),
            'is_checkins_enabled' => false,
        ])
        ->assertSessionHasErrors('is_checkins_enabled');
});

it('forbids staff without edit location hours', function (): void {
    $location = Location::factory()->create();
    $shipping = User::factory()->withRole(Role::Shipping)->worksAt($location)->create();

    $this->actingAs($shipping)
        ->put(route('dashboard.locations.checkInHours.update', ['uuid' => $location->uuid]), ['days' => hoursWeek()])
        ->assertForbidden();

    expect($location->checkinSchedule()->where('open_time', '00:00:00')->count())->toBe(7);
});

it('returns not found for a location the manager does not work at', function (): void {
    $location = Location::factory()->create();
    $manager = hoursManager(Location::factory()->create());

    $this->actingAs($manager)
        ->put(route('dashboard.locations.checkInHours.update', ['uuid' => $location->uuid]), ['days' => hoursWeek()])
        ->assertNotFound();

    expect($location->checkinSchedule()->where('open_time', '00:00:00')->count())->toBe(7);
});

it('lists the upcoming bookings the editor checks new hours against', function (): void {
    // 14:00 UTC is 09:00 in New York (EST).
    $this->travelTo('2026-12-02 14:00:00');
    $location = Location::factory()->appointmentsOnly()->create(['timezone' => 'America/New_York']);
    $booked = Appointment::factory()->atLocation($location)->scheduled()->scheduledFor(now()->addDay())->create();
    Appointment::factory()->atLocation($location)->scheduled()->scheduledFor(now()->subHour())->create();
    Appointment::factory()->atLocation($location)->cancelled()->scheduledFor(now()->addDay())->create();
    // Special hours on Dec 5 decide that day, not the weekly hours.
    AppointmentScheduleOverride::factory()->for($location)->onDate('2026-12-05')->create();
    Appointment::factory()->atLocation($location)->scheduled()->scheduledFor(now()->setDate(2026, 12, 5))->create();

    $this->actingAs(hoursManager($location))
        ->get(route('dashboard.locations.appointmentHours', ['uuid' => $location->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('otherHours', 7)
            ->has('bookedAppointments', 1)
            ->where('bookedAppointments.0', [
                'uuid' => $booked->uuid,
                'referenceNumber' => $booked->reference_number,
                'driversName' => $booked->drivers_name,
                'date' => '2026-12-03',
                'time' => '09:00',
                'dayOfWeek' => 4,
            ]));
});

it('sends no bookings to staff who cannot edit the hours', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    Appointment::factory()->atLocation($location)->scheduled()->scheduledFor(now()->addDay())->create();
    $shipping = User::factory()->withRole(Role::Shipping)->worksAt($location)->create();

    $this->actingAs($shipping)
        ->get(route('dashboard.locations.appointmentHours', ['uuid' => $location->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('bookedAppointments', []));
});
