<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Appointment;
use App\Models\AppointmentScheduleOverride;
use App\Models\Dock;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia;

/*
 * The dock view page: one location's day, with docks as columns.
 */

function dockViewer(Location $location): User
{
    return User::factory()->withRole(Role::Shipping)->worksAt($location)->create();
}

it('shows only the bookings on the local date in the location timezone', function (): void {
    $location = Location::factory()->appointmentsOnly()->create(['timezone' => 'America/Los_Angeles']);
    $date = Date::now('America/Los_Angeles')->addDays(2);
    $lateEvening = Appointment::factory()->for($location)->scheduledFor($date->copy()->setTime(23, 0))->create();
    Appointment::factory()->for($location)->scheduledFor($date->copy()->addDay()->setTime(8, 0))->create();
    Appointment::factory()->for($location)->scheduledFor($date->copy()->subDay()->setTime(8, 0))->create();
    Appointment::factory()->for($location)->scheduledFor($date->copy()->setTime(9, 0))->cancelled()->create();

    $this->actingAs(dockViewer($location))
        ->get(route('dashboard.dockView', ['location' => $location->id, 'date' => $date->toDateString()]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Dashboard/Appointments/DockView')
            ->where('day.date', $date->toDateString())
            ->where('day.timezone', 'America/Los_Angeles')
            ->has('appointments', 1)
            ->where('appointments.0.uuid', $lateEvening->uuid));
});

it('lists open docks, and a closed dock only on a day it holds a booking', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    Dock::factory()->for($location)->create(['name' => 'Door 10']);
    Dock::factory()->for($location)->create(['name' => 'Door 2']);
    $closed = Dock::factory()->for($location)->create(['name' => 'Door 3', 'is_active' => false]);
    Dock::factory()->for($location)->create(['name' => 'Walk-in', 'accepts_appointments' => false]);
    $held = Date::now($location->timezone)->addDay();
    Appointment::factory()->for($location)->scheduledFor($held->copy()->setTime(9, 0))->create(['dock_id' => $closed->id]);
    $staff = dockViewer($location);

    $this->actingAs($staff)
        ->get(route('dashboard.dockView', ['location' => $location->id, 'date' => $held->toDateString()]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('docks.0.name', 'Door 2')
            ->where('docks.1.name', 'Door 3')
            ->where('docks.1.isOpen', false)
            ->where('docks.2.name', 'Door 10')
            ->has('docks', 3));

    $this->actingAs($staff)
        ->get(route('dashboard.dockView', ['location' => $location->id, 'date' => $held->copy()->addDay()->toDateString()]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->has('docks', 2));
});

it('marks a closed day but still draws its bookings', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    $date = Date::now($location->timezone)->addDay();
    AppointmentScheduleOverride::factory()->for($location)->create(['override_date' => $date->toDateString(), 'is_closed' => true]);
    Appointment::factory()->for($location)->scheduledFor($date->copy()->setTime(6, 30))->create();

    $this->actingAs(dockViewer($location))
        ->get(route('dashboard.dockView', ['location' => $location->id, 'date' => $date->toDateString()]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('day.isClosed', true)
            ->where('day.startMinute', 360)
            ->has('appointments', 1));
});

it('opens on the first location the user may see', function (): void {
    $location = Location::factory()->create();
    Location::factory()->create();

    $this->actingAs(dockViewer($location))
        ->get(route('dashboard.dockView'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('locationId', $location->id)
            ->has('locations', 1));
});

it('hides a location the user is not attached to', function (): void {
    $elsewhere = Location::factory()->create();

    $this->actingAs(dockViewer(Location::factory()->create()))
        ->get(route('dashboard.dockView', ['location' => $elsewhere->id]))
        ->assertNotFound();
});

it('forbids users who cannot view appointments', function (): void {
    $this->actingAs(User::factory()->withRole(Role::Driver)->create())
        ->get(route('dashboard.dockView'))
        ->assertForbidden();
});
