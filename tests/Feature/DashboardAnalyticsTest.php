<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Appointment;
use App\Models\CheckIn;
use App\Models\Location;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia;

/*
 * Pins the analytics page: who may open it, and the figures in its deferred report (periods
 * bucketed in the viewer's timezone, the rates, and location scoping). Drivers, guests and
 * unverified users are covered with every other dashboard page in DashboardPagesTest.
 */

beforeEach(function (): void {
    // A Thursday; 10:00 in Chicago (CDT, UTC-5).
    $this->travelTo(CarbonImmutable::parse('2026-10-15 15:00:00', 'UTC'));
});

/**
 * @param  array<string, mixed>  $query
 */
function analyticsReport(User $user, array $query, Closure $assert): void
{
    test()->actingAs($user)
        ->get(route('dashboard.analytics', $query))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Dashboard/Analytics/Index')
            ->missing('report')
            ->loadDeferredProps(fn (AssertableInertia $reload): AssertableInertia => $assert($reload)));
}

it('opens for the roles granted view analytics', function (Role $role): void {
    $this->actingAs(User::factory()->withRole($role)->create())
        ->get(route('dashboard.analytics'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Dashboard/Analytics/Index')
            ->where('filters.period', '30d'));
})->with([Role::Admin, Role::ShippingManager, Role::Sales]);

it('is forbidden to shipping staff', function (): void {
    $this->actingAs(User::factory()->withRole(Role::Shipping)->create())
        ->get(route('dashboard.analytics'))
        ->assertForbidden();
});

it('compares check-in volume, completion rate and median turnaround with the previous period', function (): void {
    $submitted = CarbonImmutable::parse('2026-10-12 12:00:00', 'UTC');
    CheckIn::factory()->completed()->createdAt($submitted)->create(['completed_at' => $submitted->addMinutes(30)]);
    CheckIn::factory()->completed()->createdAt($submitted)->create(['completed_at' => $submitted->addMinutes(90)]);
    CheckIn::factory()->pending()->createdAt($submitted)->create();
    $previous = CarbonImmutable::parse('2026-10-05 12:00:00', 'UTC');
    CheckIn::factory()->completed()->createdAt($previous)->create(['completed_at' => $previous->addMinutes(20)]);
    // Before the previous period: counted in neither.
    CheckIn::factory()->completed()->createdAt(CarbonImmutable::parse('2026-10-01 12:00:00', 'UTC'))->create();

    analyticsReport(User::factory()->withRole(Role::Admin)->create(), ['period' => '7d', 'tz' => 'UTC'], fn (AssertableInertia $page): AssertableInertia => $page
        ->where('report.summary.checkIns', ['value' => 3, 'previous' => 1])
        ->where('report.summary.completionRate', ['value' => 66.7, 'previous' => 100])
        ->where('report.summary.medianTurnaround', ['value' => 60, 'previous' => 20]));
});

it('counts the no-show rate over appointments that were due, not scheduled or canceled ones', function (): void {
    $when = CarbonImmutable::parse('2026-10-12 14:00:00', 'UTC');
    Appointment::factory()->noShow()->scheduledFor($when)->create();
    Appointment::factory()->checkedIn()->scheduledFor($when)->create();
    Appointment::factory()->completed()->scheduledFor($when)->create();
    Appointment::factory()->scheduled()->scheduledFor($when)->create();
    Appointment::factory()->cancelled()->scheduledFor($when)->create();

    analyticsReport(User::factory()->withRole(Role::Admin)->create(), ['period' => '7d', 'tz' => 'UTC'], fn (AssertableInertia $page): AssertableInertia => $page
        ->where('report.summary.appointments', ['value' => 5, 'previous' => 0])
        ->where('report.summary.noShowRate', ['value' => 33.3, 'previous' => null]));
});

it("buckets volume, hours and weekdays in the viewer's timezone with empty days zero-filled", function (): void {
    // Tuesday 21:00 in Chicago, but already Wednesday in UTC.
    CheckIn::factory()->createdAt(CarbonImmutable::parse('2026-10-14 02:00:00', 'UTC'))->create();

    analyticsReport(User::factory()->withRole(Role::Admin)->create(), ['period' => '7d', 'tz' => 'America/Chicago'], fn (AssertableInertia $page): AssertableInertia => $page
        ->where('report.volume.bucket', 'day')
        ->where('report.volume.labels', [
            '2026-10-09T00:00:00', '2026-10-10T00:00:00', '2026-10-11T00:00:00', '2026-10-12T00:00:00',
            '2026-10-13T00:00:00', '2026-10-14T00:00:00', '2026-10-15T00:00:00',
        ])
        ->where('report.volume.checkIns', [0, 0, 0, 0, 1, 0, 0])
        ->where('report.timeOfDay.hourly.21', 1)
        ->where('report.timeOfDay.weekdays', [0, 1, 0, 0, 0, 0, 0]));
});

it('buckets today by hour', function (): void {
    CheckIn::factory()->createdAt(CarbonImmutable::parse('2026-10-15 14:30:00', 'UTC'))->create();

    analyticsReport(User::factory()->withRole(Role::Admin)->create(), ['period' => 'today', 'tz' => 'America/Chicago'], fn (AssertableInertia $page): AssertableInertia => $page
        ->where('report.volume.bucket', 'hour')
        ->has('report.volume.labels', 24)
        ->where('report.volume.labels.9', '2026-10-15T09:00:00')
        ->where('report.volume.checkIns.9', 1));
});

it('breaks check-ins and appointments down by status, with archived ones apart', function (): void {
    $when = CarbonImmutable::parse('2026-10-12 12:00:00', 'UTC');
    CheckIn::factory()->pending()->createdAt($when)->create();
    CheckIn::factory()->completed()->createdAt($when)->create();
    CheckIn::factory()->completed()->createdAt($when)->trashed()->create();
    Appointment::factory()->noShow()->scheduledFor($when)->create();
    Appointment::factory()->scheduled()->scheduledFor($when)->trashed()->create();

    analyticsReport(User::factory()->withRole(Role::Admin)->create(), ['period' => '7d'], fn (AssertableInertia $page): AssertableInertia => $page
        ->where('report.statuses.checkIns', ['pending' => 1, 'completed' => 1, 'cancelled' => 0, 'archived' => 1])
        ->where('report.statuses.appointments', [
            'scheduled' => 0, 'cancelled' => 0, 'completed' => 0, 'no-show' => 1, 'checked-in' => 0, 'archived' => 1,
        ]));
});

it('ranks locations by check-ins and narrows every figure to the chosen locations', function (): void {
    $busy = Location::factory()->create(['name' => 'Busy']);
    $quiet = Location::factory()->create(['name' => 'Quiet']);
    $when = CarbonImmutable::parse('2026-10-12 12:00:00', 'UTC');
    CheckIn::factory()->count(2)->completed()->atLocation($busy)->createdAt($when)->create();
    CheckIn::factory()->pending()->atLocation($quiet)->createdAt($when)->create();
    $admin = User::factory()->withRole(Role::Admin)->create();

    analyticsReport($admin, ['period' => '7d'], fn (AssertableInertia $page): AssertableInertia => $page
        ->where('report.summary.checkIns.value', 3)
        ->where('report.ranking.0.name', 'Busy')
        ->where('report.ranking.0.checkIns', 2)
        ->where('report.ranking.0.completionRate', 100)
        ->where('report.ranking.1.name', 'Quiet')
        ->where('report.ranking.1.completionRate', 0));

    analyticsReport($admin, ['period' => '7d', 'locations' => [$quiet->id]], fn (AssertableInertia $page): AssertableInertia => $page
        ->where('report.summary.checkIns.value', 1)
        ->has('report.ranking', 1)
        ->where('report.ranking.0.name', 'Quiet'));
});

it("never shows a location-scoped manager another location's figures, even when it asks for them", function (Role $role): void {
    $mine = Location::factory()->create();
    $other = Location::factory()->create();
    $when = CarbonImmutable::parse('2026-10-12 12:00:00', 'UTC');
    CheckIn::factory()->atLocation($mine)->createdAt($when)->create();
    CheckIn::factory()->count(2)->atLocation($other)->createdAt($when)->create();
    Appointment::factory()->atLocation($other)->scheduledFor($when)->create();
    $user = User::factory()->withRole($role)->worksAt($mine)->create();

    analyticsReport($user, ['period' => '7d'], fn (AssertableInertia $page): AssertableInertia => $page
        ->where('report.summary.checkIns.value', 1)
        ->where('report.summary.appointments.value', 0)
        ->has('report.ranking', 1)
        ->where('report.ranking.0.id', $mine->id));

    analyticsReport($user, ['period' => '7d', 'locations' => [$other->id]], fn (AssertableInertia $page): AssertableInertia => $page
        ->where('report.summary.checkIns.value', 0)
        ->has('report.ranking', 0));
})->with([Role::ShippingManager]);

it('rejects an unknown period or timezone', function (string $field, string $value): void {
    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->get(route('dashboard.analytics', [$field => $value]))
        ->assertInvalid($field);
})->with([
    'period' => ['period', 'forever'],
    'timezone' => ['tz', 'Mars/Olympus_Mons'],
]);
