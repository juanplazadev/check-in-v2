<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Appointment;
use App\Models\CheckIn;
use App\Models\Location;
use App\Models\User;
use App\Queries\DashboardCheckIns;
use App\Queries\LiveQueueAppointments;
use App\Queries\LiveQueueCheckIns;
use Inertia\Testing\AssertableInertia;

/*
 * The check-in data behind the staff dashboard: the live queue shipping keeps
 * open, the searchable table, and the detail page. Gates are pinned in
 * DashboardPagesTest.
 */

function shippingStaff(): User
{
    return User::factory()->withRole(Role::Shipping)->create();
}

it('queues only recent pending check-ins, oldest first', function (): void {
    $this->freezeTime();
    $newer = CheckIn::factory()->pending()->createdAt(now()->subMinutes(5))->create();
    $older = CheckIn::factory()->pending()->createdAt(now()->subMinutes(30))->create();
    CheckIn::factory()->completed()->createdAt(now()->subMinutes(10))->create();
    CheckIn::factory()->pending()->createdAt(now()->subHours(LiveQueueCheckIns::WINDOW_HOURS + 1))->create();

    $this->actingAs(shippingStaff())
        ->get(route('dashboard.queue'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Dashboard/Queue/Index')
            ->has('checkIns', 2)
            ->where('checkIns.0.uuid', $older->uuid)
            ->where('checkIns.1.uuid', $newer->uuid)
            ->where('dashboard.pendingCheckIns', 2));
});

it('lists scheduled appointments inside the arriving window, soonest first', function (): void {
    $this->freezeTime();
    $later = Appointment::factory()->scheduled()->scheduledFor(now()->addHours(3))->create();
    $late = Appointment::factory()->scheduled()->scheduledFor(now()->subHour())->create();
    Appointment::factory()->cancelled()->scheduledFor(now()->addHour())->create();
    Appointment::factory()->scheduled()->scheduledFor(now()->subHours(LiveQueueAppointments::LATE_GRACE_HOURS + 1))->create();
    Appointment::factory()->scheduled()->scheduledFor(now()->addHours(LiveQueueAppointments::AHEAD_HOURS + 1))->create();

    $this->actingAs(shippingStaff())
        ->get(route('dashboard.queue'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('appointments', 2)
            ->where('appointments.0.uuid', $late->uuid)
            ->where('appointments.1.uuid', $later->uuid));
});

it('pages the check-in table newest first', function (): void {
    $this->freezeTime();
    CheckIn::factory()
        ->count(DashboardCheckIns::PER_PAGE + 1)
        ->sequence(fn ($sequence): array => ['created_at' => now()->subMinutes($sequence->index)])
        ->create();
    $newest = CheckIn::query()->latest()->first();

    $this->actingAs(shippingStaff())
        ->get(route('dashboard.checkIns'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('checkIns.data', DashboardCheckIns::PER_PAGE)
            ->where('checkIns.data.0.uuid', $newest->uuid)
            ->where('checkIns.total', DashboardCheckIns::PER_PAGE + 1));
});

it('filters the check-in table by status and location, counting tabs without the status', function (): void {
    $dock = Location::factory()->create();
    $match = CheckIn::factory()->completed()->atLocation($dock)->create();
    CheckIn::factory()->pending()->atLocation($dock)->create();
    CheckIn::factory()->completed()->create();

    $this->actingAs(shippingStaff())
        ->get(route('dashboard.checkIns', ['status' => 'completed', 'locations' => [$dock->id]]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('checkIns.data', 1)
            ->where('checkIns.data.0.uuid', $match->uuid)
            ->where('statusCounts', ['all' => 2, 'pending' => 1, 'completed' => 1, 'cancelled' => 0]));
});

it('searches the check-in table across driver, plates and purchase orders', function (string $term): void {
    $match = CheckIn::factory()->create([
        'drivers_name' => 'Rosa Quintero',
        'truck_plate' => 'TRK4821',
    ]);
    $match->purchaseOrders()->create(['number' => 'PO-77310']);
    CheckIn::factory()->create(['drivers_name' => 'Someone Else', 'truck_plate' => 'ZZZ0000']);

    $this->actingAs(shippingStaff())
        ->get(route('dashboard.checkIns', ['search' => $term]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('checkIns.data', 1)
            ->where('checkIns.data.0.uuid', $match->uuid));
})->with(['driver' => 'quintero', 'plate' => 'trk48', 'purchase order' => '77310']);

it('rejects an unknown status filter', function (): void {
    $this->actingAs(shippingStaff())
        ->get(route('dashboard.checkIns', ['status' => 'lost']))
        ->assertSessionHasErrors('status');
});

it('shows a check-in with its history', function (): void {
    $checkIn = CheckIn::factory()->create();
    $checkIn->update(['status' => 'completed']);

    $this->actingAs(shippingStaff())
        ->get(route('dashboard.checkIns.show', ['uuid' => $checkIn->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('checkIn.uuid', $checkIn->uuid)
            ->where('checkIn.status', 'completed')
            ->where('checkIn.history.0.description', 'Status changed from pending to completed'));
});

it('returns not found for a check-in that does not exist', function (): void {
    $this->actingAs(shippingStaff())
        ->get(route('dashboard.checkIns.show', ['uuid' => fake()->uuid()]))
        ->assertNotFound();
});
