<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Appointment;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/*
 * The appointment PDF behind the detail page's View and Print actions: who
 * may open it, and which appointments it resolves.
 */

beforeEach(function (): void {
    Storage::fake('local');
});

it('serves the appointment pdf inline to every staff role', function (Role $role): void {
    $location = Location::factory()->create(['abbreviation' => 'agu']);
    $appointment = Appointment::factory()->atLocation($location)->create();
    $staff = User::factory()->withRole($role)->worksAt($location)->create();

    $response = $this->actingAs($staff)
        ->get(route('dashboard.appointments.pdf', ['uuid' => $appointment->uuid]));

    $response->assertSuccessful()
        ->assertHeader('content-type', 'application/pdf');

    expect($response->headers->get('content-disposition'))
        ->toBe(sprintf('inline; filename="appointment-agu-%s.pdf"', $appointment->reference_number));
})->with(Role::staff());

it('serves the pdf to a user who may only print it', function (): void {
    $location = Location::factory()->create();
    $appointment = Appointment::factory()->atLocation($location)->create();
    $user = User::factory()->worksAt($location)->create();
    $user->givePermissionTo(Permission::ViewAppointments, Permission::PrintAppointmentPdf);

    $this->actingAs($user)
        ->get(route('dashboard.appointments.pdf', ['uuid' => $appointment->uuid]))
        ->assertSuccessful();
});

it('forbids drivers from opening an appointment pdf', function (): void {
    $appointment = Appointment::factory()->create();

    $this->actingAs(User::factory()->withRole(Role::Driver)->create())
        ->get(route('dashboard.appointments.pdf', ['uuid' => $appointment->uuid]))
        ->assertForbidden();
});

it('sends a guest to sign in before opening an appointment pdf', function (): void {
    $appointment = Appointment::factory()->create();

    $this->get(route('dashboard.appointments.pdf', ['uuid' => $appointment->uuid]))
        ->assertRedirect(route('login'));
});

it('returns not found for an appointment at a location the user does not work', function (): void {
    $appointment = Appointment::factory()->create();
    $shipping = User::factory()->withRole(Role::Shipping)->worksAt(Location::factory()->create())->create();

    $this->actingAs($shipping)
        ->get(route('dashboard.appointments.pdf', ['uuid' => $appointment->uuid]))
        ->assertNotFound();
});
