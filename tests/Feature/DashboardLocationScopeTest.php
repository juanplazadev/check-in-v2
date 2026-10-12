<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Appointment;
use App\Models\CheckIn;
use App\Models\Location;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/*
 * Shipping and shipping managers see only the locations they are attached to;
 * admin and sales see every location. Covers each dashboard read. Who gets the
 * bell notification and the live push is pinned in CheckInFormTest,
 * AppointmentStoreTest and LiveCheckInFeedTest.
 */

it('limits a location-scoped role to its own locations across the live queue and badge', function (Role $role): void {
    $mine = Location::factory()->create();
    $here = CheckIn::factory()->pending()->atLocation($mine)->create();
    CheckIn::factory()->pending()->create();
    $booked = Appointment::factory()->scheduled()->scheduledFor(now()->addHour())->atLocation($mine)->create();
    Appointment::factory()->scheduled()->scheduledFor(now()->addHour())->create();

    $this->actingAs(User::factory()->withRole($role)->worksAt($mine)->create())
        ->get(route('dashboard.queue'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('checkIns', 1)
            ->where('checkIns.0.uuid', $here->uuid)
            ->has('appointments', 1)
            ->where('appointments.0.uuid', $booked->uuid)
            ->where('dashboard.pendingCheckIns', 1)
            ->where('locations', [['id' => $mine->id, 'name' => $mine->name]]));
})->with(Role::locationScoped());

it("limits the check-in table and its tab counts to the user's locations", function (): void {
    $mine = Location::factory()->create();
    $here = CheckIn::factory()->pending()->atLocation($mine)->create();
    CheckIn::factory()->pending()->create();

    $this->actingAs(User::factory()->withRole(Role::Shipping)->worksAt($mine)->create())
        ->get(route('dashboard.checkIns'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('checkIns.data', 1)
            ->where('checkIns.data.0.uuid', $here->uuid)
            ->where('statusCounts.all', 1));
});

it("limits the archived tab to the user's locations", function (): void {
    $mine = Location::factory()->create();
    $here = CheckIn::factory()->atLocation($mine)->create();
    $here->delete();
    CheckIn::factory()->create()->delete();

    $this->actingAs(User::factory()->withRole(Role::Shipping)->worksAt($mine)->create())
        ->get(route('dashboard.checkIns', ['status' => 'archived']))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('checkIns.data', 1)
            ->where('checkIns.data.0.uuid', $here->uuid)
            ->where('statusCounts.archived', 1));
});

it('does not widen the scope through the location filter', function (): void {
    $elsewhere = Location::factory()->create();
    CheckIn::factory()->atLocation($elsewhere)->create();

    $this->actingAs(User::factory()->withRole(Role::Shipping)->worksAt(Location::factory()->create())->create())
        ->get(route('dashboard.checkIns', ['locations' => [$elsewhere->id]]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('checkIns.data', 0));
});

it('returns not found for a record at another location', function (Closure $url): void {
    $this->actingAs(User::factory()->withRole(Role::Shipping)->worksAt(Location::factory()->create())->create())
        ->get($url())
        ->assertNotFound();
})->with([
    'check-in' => fn (): string => route('dashboard.checkIns.show', ['uuid' => CheckIn::factory()->create()->uuid]),
    'archived check-in' => fn (): string => route('dashboard.checkIns.show', ['uuid' => tap(CheckIn::factory()->create())->delete()->uuid]),
    'appointment' => fn (): string => route('dashboard.appointments.show', ['uuid' => Appointment::factory()->create()->uuid]),
]);

it('shows nothing to a location-scoped user attached to no location', function (): void {
    CheckIn::factory()->pending()->create();

    $this->actingAs(User::factory()->withRole(Role::ShippingManager)->create())
        ->get(route('dashboard.queue'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('checkIns', 0)
            ->where('dashboard.pendingCheckIns', 0)
            ->where('locations', []));
});

it('shows every location to the all-locations roles, whatever they are attached to', function (User $user): void {
    CheckIn::factory()->pending()->count(2)->create();

    $this->actingAs($user)
        ->get(route('dashboard.queue'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('checkIns', 2)
            ->has('locations', Location::query()->count()));
})->with([
    'admin' => fn (): User => User::factory()->withRole(Role::Admin)->create(),
    'sales' => fn (): User => User::factory()->withRole(Role::Sales)->worksAt(Location::factory()->create())->create(),
    'admin who also holds shipping' => fn (): User => User::factory()->withRole(Role::Admin)->withRole(Role::Shipping)->create(),
]);
