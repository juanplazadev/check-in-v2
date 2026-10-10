<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Appointment;
use App\Models\CheckIn;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/*
 * The appointment detail page on the staff dashboard. Gates are pinned in
 * DashboardPagesTest, location scoping in DashboardLocationScopeTest.
 */

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

it('returns not found for an appointment that does not exist', function (): void {
    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->get(route('dashboard.appointments.show', ['uuid' => fake()->uuid()]))
        ->assertNotFound();
});
