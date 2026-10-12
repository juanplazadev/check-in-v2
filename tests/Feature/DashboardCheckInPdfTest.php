<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\CheckIn;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/*
 * The check-in PDF behind the detail page's View and Print actions: who may
 * open it, and which check-ins it resolves.
 */

beforeEach(function (): void {
    Storage::fake('local');
});

it('serves the check-in pdf inline to every staff role', function (Role $role): void {
    $location = Location::factory()->create(['abbreviation' => 'agu']);
    $checkIn = CheckIn::factory()->atLocation($location)->create();
    $staff = User::factory()->withRole($role)->worksAt($location)->create();

    $response = $this->actingAs($staff)
        ->get(route('dashboard.checkIns.pdf', ['uuid' => $checkIn->uuid]));

    $response->assertSuccessful()
        ->assertHeader('content-type', 'application/pdf');

    expect($response->headers->get('content-disposition'))
        ->toBe(sprintf('inline; filename="check-in-agu-%s.pdf"', $checkIn->reference_number));
})->with(Role::staff());

it('serves the pdf to a user who may only print it', function (): void {
    $location = Location::factory()->create();
    $checkIn = CheckIn::factory()->atLocation($location)->create();
    $user = User::factory()->worksAt($location)->create();
    $user->givePermissionTo(Permission::ViewCheckIns, Permission::PrintCheckInPdf);

    $this->actingAs($user)
        ->get(route('dashboard.checkIns.pdf', ['uuid' => $checkIn->uuid]))
        ->assertSuccessful();
});

it('forbids drivers from opening a check-in pdf', function (): void {
    $checkIn = CheckIn::factory()->create();

    $this->actingAs(User::factory()->withRole(Role::Driver)->create())
        ->get(route('dashboard.checkIns.pdf', ['uuid' => $checkIn->uuid]))
        ->assertForbidden();
});

it('sends a guest to sign in before opening a check-in pdf', function (): void {
    $checkIn = CheckIn::factory()->create();

    $this->get(route('dashboard.checkIns.pdf', ['uuid' => $checkIn->uuid]))
        ->assertRedirect(route('login'));
});

it('returns not found for a check-in at a location the user does not work', function (): void {
    $checkIn = CheckIn::factory()->create();
    $shipping = User::factory()->withRole(Role::Shipping)->worksAt(Location::factory()->create())->create();

    $this->actingAs($shipping)
        ->get(route('dashboard.checkIns.pdf', ['uuid' => $checkIn->uuid]))
        ->assertNotFound();
});

it('serves the pdf of an archived check-in, which the detail page still opens', function (): void {
    $checkIn = CheckIn::factory()->create();
    $checkIn->delete();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->get(route('dashboard.checkIns.pdf', ['uuid' => $checkIn->uuid]))
        ->assertSuccessful();
});
