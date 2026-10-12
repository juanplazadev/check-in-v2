<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Appointment;
use App\Models\CheckIn;
use App\Models\Location;
use App\Models\User;
use App\Queries\DashboardAppointments;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia;

/*
 * The appointment table and detail page on the staff dashboard. Gates are
 * pinned in DashboardPagesTest, location scoping in DashboardLocationScopeTest.
 */

/**
 * Per-status tab counts in the order the server sends them.
 *
 * @param  array<string, int>  $counts
 * @return array<string, int>
 */
function appointmentCounts(array $counts): array
{
    return [
        'all' => 0, 'scheduled' => 0, 'cancelled' => 0, 'completed' => 0,
        'no-show' => 0, 'checked-in' => 0, 'archived' => 0,
        ...$counts,
    ];
}

it('pages the appointment table latest slot first', function (): void {
    $this->freezeTime();
    Appointment::factory()
        ->count(DashboardAppointments::DEFAULT_PER_PAGE + 1)
        ->sequence(fn ($sequence): array => ['scheduled_for' => now()->addHours($sequence->index)])
        ->create();
    $latest = Appointment::query()->orderByDesc('scheduled_for')->first();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->get(route('dashboard.appointments'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Dashboard/Appointments/Index')
            ->has('appointments.data', DashboardAppointments::DEFAULT_PER_PAGE)
            ->where('appointments.data.0.uuid', $latest->uuid)
            ->where('appointments.total', DashboardAppointments::DEFAULT_PER_PAGE + 1));
});

it('offers every location the user may see in the filter, including ones that no longer take appointments', function (): void {
    $bookingsOff = Location::factory()->create(['is_appointments_enabled' => false]);
    $bookingsOn = Location::factory()->appointmentsOnly()->create();
    Location::factory()->create();
    $user = User::factory()->withRole(Role::Shipping)->worksAt($bookingsOff, $bookingsOn)->create();

    $this->actingAs($user)
        ->get(route('dashboard.appointments'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('locations', fn ($locations): bool => collect($locations)->pluck('id')->sort()->values()->all()
                === collect([$bookingsOff->id, $bookingsOn->id])->sort()->values()->all()));
});

it('lists appointments at a location that has since turned bookings off', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    $appointment = Appointment::factory()->atLocation($location)->create();
    $location->update(['is_appointments_enabled' => false]);

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->get(route('dashboard.appointments', ['locations' => [$location->id]]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('appointments.data', 1)
            ->where('appointments.data.0.uuid', $appointment->uuid));
});

it('filters the appointment table by status and location, counting tabs without the status', function (): void {
    $dock = Location::factory()->create();
    $match = Appointment::factory()->cancelled()->atLocation($dock)->create();
    Appointment::factory()->scheduled()->atLocation($dock)->create();
    Appointment::factory()->cancelled()->create();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->get(route('dashboard.appointments', ['status' => 'cancelled', 'locations' => [$dock->id]]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('appointments.data', 1)
            ->where('appointments.data.0.uuid', $match->uuid)
            ->where('statusCounts', appointmentCounts(['all' => 2, 'scheduled' => 1, 'cancelled' => 1])));
});

it('lists only archived appointments under the archived tab, leaving them out of every other count', function (): void {
    $archived = Appointment::factory()->completed()->create();
    $archived->delete();
    Appointment::factory()->scheduled()->create();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->get(route('dashboard.appointments', ['status' => 'archived']))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('appointments.data', 1)
            ->where('appointments.data.0.uuid', $archived->uuid)
            ->where('statusCounts', appointmentCounts(['all' => 1, 'scheduled' => 1, 'archived' => 1])));
});

it('searches the appointment table by reference, driver, phone and purchase order', function (string $term): void {
    $match = Appointment::factory()->create([
        'reference_number' => 'APTX4821',
        'drivers_name' => 'Maria Quintero',
        'drivers_cellphone' => '+17875550142',
    ]);
    $match->purchaseOrders()->create(['number' => 'PO-77310']);
    Appointment::factory()->create(['drivers_name' => 'John Smith', 'drivers_cellphone' => '+13055559999'])
        ->purchaseOrders()->create(['number' => 'PO-11111']);

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->get(route('dashboard.appointments', ['search' => $term]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('appointments.data', 1)
            ->where('appointments.data.0.uuid', $match->uuid));
})->with([
    'reference' => 'aptx48',
    'driver' => 'quintero',
    'phone' => '(787) 555-0142',
    'purchase order' => '77310',
]);

it('searches the appointment table by exact uuid', function (): void {
    $match = Appointment::factory()->create();
    Appointment::factory()->create();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->get(route('dashboard.appointments', ['search' => mb_strtoupper($match->uuid)]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('appointments.data', 1)
            ->where('appointments.data.0.uuid', $match->uuid));
});

it('ignores an appointment search shorter than the minimum length', function (): void {
    Appointment::factory()->count(2)->create();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->get(route('dashboard.appointments', ['search' => 'ab']))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('appointments.data', 2)
            ->where('filters.search', null));
});

it('filters the appointment table to whole days of the scheduled slot, including future days', function (): void {
    $this->travelTo(Date::parse('2026-10-11 12:00:00'));
    $firstDay = Appointment::factory()->create(['scheduled_for' => Date::parse('2026-10-20 00:00:00')]);
    $lastDay = Appointment::factory()->create(['scheduled_for' => Date::parse('2026-10-22 23:59:59')]);
    Appointment::factory()->create(['scheduled_for' => Date::parse('2026-10-19 23:59:59')]);
    Appointment::factory()->create(['scheduled_for' => Date::parse('2026-10-23 00:00:00')]);

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->get(route('dashboard.appointments', ['date_field' => 'scheduled', 'from' => '2026-10-20', 'to' => '2026-10-22', 'tz' => 'UTC']))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('appointments.data', 2)
            ->where('appointments.data.0.uuid', $lastDay->uuid)
            ->where('appointments.data.1.uuid', $firstDay->uuid)
            ->where('statusCounts.all', 2));
});

it('filters the appointment table by booking date', function (): void {
    $match = Appointment::factory()->create(['created_at' => Date::parse('2026-10-01 09:00:00')]);
    Appointment::factory()->create(['created_at' => Date::parse('2026-10-05 09:00:00')]);

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->get(route('dashboard.appointments', ['date_field' => 'booked', 'from' => '2026-10-01', 'to' => '2026-10-01', 'tz' => 'UTC']))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('appointments.data', 1)
            ->where('appointments.data.0.uuid', $match->uuid));
});

it('ignores the tab, location and date filters without the filter appointments permission', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::ViewAppointments);

    $this->actingAs($user)
        ->get(route('dashboard.appointments', [
            'status' => 'cancelled',
            'locations' => [Location::factory()->create()->id],
            'date_field' => 'scheduled', 'from' => '2026-10-01', 'to' => '2026-10-02',
        ]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('filters.status', null)
            ->where('filters.locations', [])
            ->where('filters.dateField', null));
});

it('sorts the appointment table by a column in either direction', function (string $direction, array $expected): void {
    Appointment::factory()->create(['drivers_name' => 'Bravo']);
    Appointment::factory()->create(['drivers_name' => 'Alpha']);
    Appointment::factory()->create(['drivers_name' => 'Charlie']);

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->get(route('dashboard.appointments', ['sort' => 'driver', 'direction' => $direction]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('appointments.data', fn ($rows): bool => collect($rows)->pluck('driver.name')->all() === $expected));
})->with([
    'ascending' => ['asc', ['Alpha', 'Bravo', 'Charlie']],
    'descending' => ['desc', ['Charlie', 'Bravo', 'Alpha']],
]);

it('rejects an invalid appointment table filter', function (array $query, string $field): void {
    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->get(route('dashboard.appointments', $query))
        ->assertSessionHasErrors($field);
})->with([
    'unknown status' => [['status' => 'lost'], 'status'],
    'unsortable column' => [['sort' => 'purchaseOrders'], 'sort'],
    'check-in date field' => [['date_field' => 'submitted', 'from' => '2026-10-01', 'to' => '2026-10-02'], 'date_field'],
    'range without an end' => [['date_field' => 'scheduled', 'from' => '2026-10-01'], 'to'],
]);

it('shows an appointment with its history and the check-in it became', function (): void {
    $appointment = Appointment::factory()->create(['drivers_name' => 'Jane Driver']);
    $appointment->update(['status' => 'checked-in']);

    $checkIn = CheckIn::factory()->create(['appointment_id' => $appointment->id]);

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->get(route('dashboard.appointments.show', ['uuid' => $appointment->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Dashboard/Appointments/Show')
            ->where('appointment.uuid', $appointment->uuid)
            ->where('appointment.driver.name', 'Jane Driver')
            ->where('appointment.status', 'checked-in')
            ->where('appointment.checkIn', [
                'uuid' => $checkIn->uuid,
                'referenceNumber' => $checkIn->reference_number,
            ])
            ->where('appointment.history.0.description', 'Status changed from scheduled to checked-in'));
});

it('shows an archived appointment', function (): void {
    $appointment = Appointment::factory()->create();
    $appointment->delete();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->get(route('dashboard.appointments.show', ['uuid' => $appointment->uuid]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('appointment.uuid', $appointment->uuid));
});

it('returns not found for an appointment that does not exist', function (): void {
    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->get(route('dashboard.appointments.show', ['uuid' => fake()->uuid()]))
        ->assertNotFound();
});
