<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Appointment;
use App\Models\CheckIn;
use App\Models\Location;
use App\Models\User;
use App\Queries\DashboardCheckIns;
use App\Queries\LiveQueueAppointments;
use App\Queries\LiveQueueCheckIns;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

/*
 * The check-in data behind the staff dashboard: the live queue shipping keeps
 * open, the searchable table, and the detail page. Gates are pinned in
 * DashboardPagesTest, location scoping in DashboardLocationScopeTest.
 */

/**
 * Sees every location, so these tests are about the queries, not the scope.
 */
function allLocationsStaff(): User
{
    return User::factory()->withRole(Role::Admin)->create();
}

it('queues only recent pending check-ins, oldest first', function (): void {
    $this->freezeTime();
    $newer = CheckIn::factory()->pending()->createdAt(now()->subMinutes(5))->create();
    $older = CheckIn::factory()->pending()->createdAt(now()->subMinutes(30))->create();
    CheckIn::factory()->completed()->createdAt(now()->subMinutes(10))->create();
    CheckIn::factory()->pending()->createdAt(now()->subHours(LiveQueueCheckIns::WINDOW_HOURS + 1))->create();

    $this->actingAs(allLocationsStaff())
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

    $this->actingAs(allLocationsStaff())
        ->get(route('dashboard.queue'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('appointments', 2)
            ->where('appointments.0.uuid', $late->uuid)
            ->where('appointments.1.uuid', $later->uuid));
});

it('pages the check-in table newest first', function (): void {
    $this->freezeTime();
    CheckIn::factory()
        ->count(DashboardCheckIns::DEFAULT_PER_PAGE + 1)
        ->sequence(fn ($sequence): array => ['created_at' => now()->subMinutes($sequence->index)])
        ->create();
    $newest = CheckIn::query()->latest()->first();

    $this->actingAs(allLocationsStaff())
        ->get(route('dashboard.checkIns'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('checkIns.data', DashboardCheckIns::DEFAULT_PER_PAGE)
            ->where('checkIns.data.0.uuid', $newest->uuid)
            ->where('checkIns.total', DashboardCheckIns::DEFAULT_PER_PAGE + 1));
});

it('pages the check-in table at the chosen page size', function (): void {
    CheckIn::factory()->count(11)->create();

    $this->actingAs(allLocationsStaff())
        ->get(route('dashboard.checkIns', ['per_page' => 10, 'page' => 2]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('checkIns.data', 1)
            ->where('checkIns.per_page', 10)
            ->where('checkIns.total', 11)
            ->where('filters.perPage', 10));
});

it('rejects a page size outside the offered options', function (): void {
    $this->actingAs(allLocationsStaff())
        ->get(route('dashboard.checkIns', ['per_page' => 1000]))
        ->assertSessionHasErrors('per_page');
});

/**
 * Every query that reads check_ins rows or counts, eager loads aside.
 *
 * @return list<string>
 */
function checkInQueriesDuring(Closure $request): array
{
    DB::enableQueryLog();
    $request();

    return array_values(array_filter(
        array_column(DB::getQueryLog(), 'query'),
        fn (string $sql): bool => str_contains($sql, 'from "check_ins"'),
    ));
}

it('reads the check-in table with one counts query and one page query', function (): void {
    CheckIn::factory()->count(3)->create();
    $staff = allLocationsStaff();

    $queries = checkInQueriesDuring(fn () => $this->actingAs($staff)->get(route('dashboard.checkIns')));

    // The sidebar's pending badge, the grouped tab counts, then the page itself.
    expect($queries)->toHaveCount(3)
        ->and($queries[1])->toContain('group by')
        ->and($queries[2])->toContain('limit 25');
});

it('reloads only the tab counts without querying the page', function (): void {
    CheckIn::factory()->create();
    $staff = allLocationsStaff();

    $queries = checkInQueriesDuring(fn () => $this->actingAs($staff)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) resolve(HandleInertiaRequests::class)->version(request()),
            'X-Inertia-Partial-Component' => 'Dashboard/CheckIns/Index',
            'X-Inertia-Partial-Data' => 'statusCounts',
        ])
        ->get(route('dashboard.checkIns')));

    expect($queries)->toHaveCount(1)
        ->and($queries[0])->toContain('group by');
});

it('carries the driver account uuid in the check-in table, null for guests', function (): void {
    $this->freezeTime();
    $driver = User::factory()->create();
    CheckIn::factory()->forUser($driver)->createdAt(now())->create();
    CheckIn::factory()->createdAt(now()->subMinute())->create();

    $this->actingAs(allLocationsStaff())
        ->get(route('dashboard.checkIns'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('checkIns.data.0.driver.userUuid', $driver->uuid)
            ->where('checkIns.data.1.driver.userUuid', null));
});

it('carries the location uuid in the check-in table', function (): void {
    $checkIn = CheckIn::factory()->create();

    $this->actingAs(allLocationsStaff())
        ->get(route('dashboard.checkIns'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('checkIns.data.0.location.uuid', $checkIn->location->uuid));
});

it('filters the check-in table by status and location, counting tabs without the status', function (): void {
    $dock = Location::factory()->create();
    $match = CheckIn::factory()->completed()->atLocation($dock)->create();
    CheckIn::factory()->pending()->atLocation($dock)->create();
    CheckIn::factory()->completed()->create();

    $this->actingAs(allLocationsStaff())
        ->get(route('dashboard.checkIns', ['status' => 'completed', 'locations' => [$dock->id]]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('checkIns.data', 1)
            ->where('checkIns.data.0.uuid', $match->uuid)
            ->where('statusCounts', ['all' => 2, 'pending' => 1, 'completed' => 1, 'cancelled' => 0, 'archived' => 0]));
});

it('searches the check-in table across driver, plates and purchase orders', function (string $term): void {
    $match = CheckIn::factory()->create([
        'drivers_name' => 'Rosa Quintero',
        'truck_plate' => 'TRK4821',
    ]);
    $match->purchaseOrders()->create(['number' => 'PO-77310']);
    CheckIn::factory()->create(['drivers_name' => 'Someone Else', 'truck_plate' => 'ZZZ0000']);

    $this->actingAs(allLocationsStaff())
        ->get(route('dashboard.checkIns', ['search' => $term]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('checkIns.data', 1)
            ->where('checkIns.data.0.uuid', $match->uuid));
})->with(['driver' => 'quintero', 'plate' => 'trk48', 'purchase order' => '77310']);

it('searches the check-in table by driver phone in any format', function (string $term): void {
    $match = CheckIn::factory()->create(['drivers_cellphone' => '+17875550142']);
    CheckIn::factory()->create(['drivers_cellphone' => '+13055559999']);

    $this->actingAs(allLocationsStaff())
        ->get(route('dashboard.checkIns', ['search' => $term]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('checkIns.data', 1)
            ->where('checkIns.data.0.uuid', $match->uuid));
})->with(['digits' => '7875550142', 'formatted' => '(787) 555-0142', 'partial' => '555-01']);

it('does not match phone digits inside a term with letters', function (): void {
    CheckIn::factory()->create([
        'drivers_cellphone' => '+17875550142',
        'truck_plate' => 'ABC1234',
        'trailer_plate' => 'DEF5678',
    ]);

    $this->actingAs(allLocationsStaff())
        ->get(route('dashboard.checkIns', ['search' => 'XQZ0142']))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('checkIns.data', 0));
});

it('ignores a table search shorter than the minimum length', function (): void {
    CheckIn::factory()->count(2)->create();

    $this->actingAs(allLocationsStaff())
        ->get(route('dashboard.checkIns', ['search' => 'ro']))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('checkIns.data', 2)
            ->where('filters.search', null));
});

it('searches the check-in table by exact uuid regardless of case', function (): void {
    $match = CheckIn::factory()->create();
    CheckIn::factory()->create();

    $this->actingAs(allLocationsStaff())
        ->get(route('dashboard.checkIns', ['search' => Str::upper($match->uuid)]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('checkIns.data', 1)
            ->where('checkIns.data.0.uuid', $match->uuid));
});

it('treats LIKE wildcards in the table search as literal characters', function (): void {
    CheckIn::factory()->create();

    $this->actingAs(allLocationsStaff())
        ->get(route('dashboard.checkIns', ['search' => '%%%']))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('checkIns.data', 0));
});

it('rejects a table search with control characters', function (): void {
    $this->actingAs(allLocationsStaff())
        ->get(route('dashboard.checkIns', ['search' => "ab\0cd"]))
        ->assertSessionHasErrors('search');
});

it('lists only archived check-ins under the archived tab, leaving them out of every other count', function (): void {
    $archived = CheckIn::factory()->completed()->create();
    $archived->delete();
    CheckIn::factory()->pending()->create();

    $this->actingAs(allLocationsStaff())
        ->get(route('dashboard.checkIns', ['status' => DashboardCheckIns::ARCHIVED]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('checkIns.data', 1)
            ->where('checkIns.data.0.uuid', $archived->uuid)
            ->where('checkIns.data.0.status', 'completed')
            ->whereNot('checkIns.data.0.archivedAt', null)
            ->where('statusCounts', ['all' => 1, 'pending' => 1, 'completed' => 0, 'cancelled' => 0, 'archived' => 1]));
});

it('searches archived check-ins by purchase order under the archived tab', function (): void {
    $archived = CheckIn::factory()->create();
    $archived->purchaseOrders()->create(['number' => 'PO-55120']);
    $archived->delete();
    CheckIn::factory()->create()->purchaseOrders()->create(['number' => 'PO-55121']);

    $this->actingAs(allLocationsStaff())
        ->get(route('dashboard.checkIns', ['status' => DashboardCheckIns::ARCHIVED, 'search' => '5512']))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('checkIns.data', 1)
            ->where('checkIns.data.0.uuid', $archived->uuid)
            ->where('checkIns.total', 1)
            ->where('statusCounts.all', 1)
            ->where('statusCounts.archived', 1));
});

it('leaves archived check-ins out of the all tab', function (): void {
    CheckIn::factory()->create()->delete();
    $live = CheckIn::factory()->create();

    $this->actingAs(allLocationsStaff())
        ->get(route('dashboard.checkIns'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('checkIns.data', 1)
            ->where('checkIns.data.0.uuid', $live->uuid)
            ->where('checkIns.data.0.archivedAt', null));
});

it('rejects an unknown status filter', function (): void {
    $this->actingAs(allLocationsStaff())
        ->get(route('dashboard.checkIns', ['status' => 'lost']))
        ->assertSessionHasErrors('status');
});

it('filters the check-in table to whole days of the submitted date, counting tabs within the range', function (): void {
    $firstDay = CheckIn::factory()->pending()->createdAt(Date::parse('2026-10-01 00:00:00'))->create();
    $lastDay = CheckIn::factory()->completed()->createdAt(Date::parse('2026-10-03 23:59:59'))->create();
    CheckIn::factory()->pending()->createdAt(Date::parse('2026-09-30 23:59:59'))->create();
    CheckIn::factory()->pending()->createdAt(Date::parse('2026-10-04 00:00:00'))->create();

    $this->actingAs(allLocationsStaff())
        ->get(route('dashboard.checkIns', ['date_field' => 'submitted', 'from' => '2026-10-01', 'to' => '2026-10-03', 'tz' => 'UTC']))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('checkIns.data', 2)
            ->where('checkIns.data.0.uuid', $lastDay->uuid)
            ->where('checkIns.data.1.uuid', $firstDay->uuid)
            ->where('statusCounts', ['all' => 2, 'pending' => 1, 'completed' => 1, 'cancelled' => 0, 'archived' => 0])
            ->where('filters.dateField', 'submitted')
            ->where('filters.from', '2026-10-01')
            ->where('filters.to', '2026-10-03'));
});

it('reads the date range in the viewer timezone', function (string $timezone, int $expected): void {
    // 03:00 UTC on Oct 10 is still the evening of Oct 9 in Chicago.
    CheckIn::factory()->createdAt(Date::parse('2026-10-10 03:00:00', 'UTC'))->create();

    $this->actingAs(allLocationsStaff())
        ->get(route('dashboard.checkIns', ['date_field' => 'submitted', 'from' => '2026-10-09', 'to' => '2026-10-09', 'tz' => $timezone]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('checkIns.total', $expected));
})->with([
    'Chicago' => ['America/Chicago', 1],
    'UTC' => ['UTC', 0],
]);

it('filters the check-in table by completed date, leaving out check-ins never completed', function (): void {
    $completedInRange = CheckIn::factory()->completed()->createdAt(Date::parse('2026-09-01'))->create(['completed_at' => Date::parse('2026-10-02 12:00:00')]);
    CheckIn::factory()->completed()->createdAt(Date::parse('2026-10-02 12:00:00'))->create(['completed_at' => Date::parse('2026-10-05 12:00:00')]);
    CheckIn::factory()->pending()->createdAt(Date::parse('2026-10-02 12:00:00'))->create();

    $this->actingAs(allLocationsStaff())
        ->get(route('dashboard.checkIns', ['date_field' => 'completed', 'from' => '2026-10-01', 'to' => '2026-10-03', 'tz' => 'UTC']))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('checkIns.data', 1)
            ->where('checkIns.data.0.uuid', $completedInRange->uuid)
            ->where('checkIns.data.0.completedAt', '2026-10-02T12:00:00+00:00'));
});

it('filters the check-in table by ERP sync date, leaving out check-ins never synced', function (): void {
    $syncedInRange = CheckIn::factory()->completed()->erpSynced()->createdAt(Date::parse('2026-09-01'))->create(['erp_synced_at' => Date::parse('2026-10-02 12:00:00')]);
    CheckIn::factory()->completed()->erpSynced()->createdAt(Date::parse('2026-10-02 12:00:00'))->create(['erp_synced_at' => Date::parse('2026-10-05 12:00:00')]);
    CheckIn::factory()->completed()->createdAt(Date::parse('2026-10-02 12:00:00'))->create();

    $this->actingAs(allLocationsStaff())
        ->get(route('dashboard.checkIns', ['date_field' => 'erp_synced', 'from' => '2026-10-01', 'to' => '2026-10-03', 'tz' => 'UTC']))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('checkIns.data', 1)
            ->where('checkIns.data.0.uuid', $syncedInRange->uuid)
            ->where('checkIns.data.0.erpSyncedAt', '2026-10-02T12:00:00+00:00'));
});

it('rejects an invalid date range filter', function (array $query, string $field): void {
    $this->actingAs(allLocationsStaff())
        ->get(route('dashboard.checkIns', $query))
        ->assertSessionHasErrors($field);
})->with([
    'unknown date field' => [['date_field' => 'updated_at', 'from' => '2026-10-01', 'to' => '2026-10-02'], 'date_field'],
    'SQL in the date field' => [['date_field' => 'created_at) or 1=1--', 'from' => '2026-10-01', 'to' => '2026-10-02'], 'date_field'],
    'range without a date field' => [['from' => '2026-10-01', 'to' => '2026-10-02'], 'date_field'],
    'malformed start' => [['date_field' => 'submitted', 'from' => '10/01/2026', 'to' => '2026-10-02'], 'from'],
    'SQL in the start' => [['date_field' => 'submitted', 'from' => "2026-10-01' or '1'='1", 'to' => '2026-10-02'], 'from'],
    'end before start' => [['date_field' => 'submitted', 'from' => '2026-10-05', 'to' => '2026-10-02'], 'to'],
    'start without end' => [['date_field' => 'submitted', 'from' => '2026-10-01'], 'to'],
    'end without start' => [['date_field' => 'submitted', 'to' => '2026-10-01'], 'from'],
    'unknown timezone' => [['date_field' => 'submitted', 'from' => '2026-10-01', 'to' => '2026-10-02', 'tz' => 'Mars/Olympus'], 'tz'],
]);

it('sorts the check-in table by a column in either direction', function (string $direction, array $expected): void {
    CheckIn::factory()->create(['drivers_name' => 'Bea']);
    CheckIn::factory()->create(['drivers_name' => 'Cal']);
    CheckIn::factory()->create(['drivers_name' => 'Ada']);

    $this->actingAs(allLocationsStaff())
        ->get(route('dashboard.checkIns', ['sort' => 'driver', 'direction' => $direction]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('checkIns.data.0.driver.name', $expected[0])
            ->where('checkIns.data.1.driver.name', $expected[1])
            ->where('checkIns.data.2.driver.name', $expected[2])
            ->where('filters.sort', 'driver')
            ->where('filters.direction', $direction));
})->with([
    'ascending' => ['asc', ['Ada', 'Bea', 'Cal']],
    'descending' => ['desc', ['Cal', 'Bea', 'Ada']],
]);

it('sorts empty values last in either direction', function (string $direction, array $expected): void {
    CheckIn::factory()->create(['empty_weight_lbs' => 30000]);
    CheckIn::factory()->create(['empty_weight_lbs' => null]);
    CheckIn::factory()->create(['empty_weight_lbs' => 20000]);

    $this->actingAs(allLocationsStaff())
        ->get(route('dashboard.checkIns', ['sort' => 'emptyWeight', 'direction' => $direction]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('checkIns.data.0.emptyWeightLbs', $expected[0])
            ->where('checkIns.data.1.emptyWeightLbs', $expected[1])
            ->where('checkIns.data.2.emptyWeightLbs', null));
})->with([
    'ascending' => ['asc', [20000, 30000]],
    'descending' => ['desc', [30000, 20000]],
]);

it('sorts the date column by the date being filtered on', function (): void {
    // Submitted in the opposite order to completed, so the two sorts disagree.
    $completedFirst = CheckIn::factory()->completed()->createdAt(Date::parse('2026-10-02 12:00:00'))->create(['completed_at' => Date::parse('2026-10-03 08:00:00')]);
    $completedLast = CheckIn::factory()->completed()->createdAt(Date::parse('2026-10-01 12:00:00'))->create(['completed_at' => Date::parse('2026-10-03 18:00:00')]);

    $this->actingAs(allLocationsStaff())
        ->get(route('dashboard.checkIns', ['sort' => 'date', 'direction' => 'asc', 'date_field' => 'completed', 'from' => '2026-10-01', 'to' => '2026-10-05', 'tz' => 'UTC']))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('checkIns.data.0.uuid', $completedFirst->uuid)
            ->where('checkIns.data.1.uuid', $completedLast->uuid));
});

it('sorts the check-in table by location name', function (): void {
    $zulu = CheckIn::factory()->atLocation(Location::factory()->create(['name' => 'Zulu Yard']))->create();
    $alpha = CheckIn::factory()->atLocation(Location::factory()->create(['name' => 'Alpha Dock']))->create();

    $this->actingAs(allLocationsStaff())
        ->get(route('dashboard.checkIns', ['sort' => 'location']))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('checkIns.data.0.uuid', $alpha->uuid)
            ->where('checkIns.data.1.uuid', $zulu->uuid)
            ->where('filters.direction', 'asc'));
});

it('rejects an invalid sort', function (array $query, string $field): void {
    $this->actingAs(allLocationsStaff())
        ->get(route('dashboard.checkIns', $query))
        ->assertSessionHasErrors($field);
})->with([
    'unknown column' => [['sort' => 'drivers_license_number'], 'sort'],
    'unsortable column' => [['sort' => 'equipment'], 'sort'],
    'SQL in the column' => [['sort' => 'id; drop table check_ins'], 'sort'],
    'unknown direction' => [['sort' => 'driver', 'direction' => 'sideways'], 'direction'],
]);

it('shows a check-in with its history', function (): void {
    $checkIn = CheckIn::factory()->create();
    $checkIn->update(['status' => 'completed']);

    $this->actingAs(allLocationsStaff())
        ->get(route('dashboard.checkIns.show', ['uuid' => $checkIn->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('checkIn.uuid', $checkIn->uuid)
            ->where('checkIn.status', 'completed')
            ->where('checkIn.history.0.description', 'Status changed from pending to completed'));
});

it('shares the location check-in form fields with the check-in', function (): void {
    $location = Location::factory()->create(['config' => ['checkin' => ['show_truck_color' => true]]]);
    $checkIn = CheckIn::factory()->atLocation($location)->create();

    $this->actingAs(allLocationsStaff())
        ->get(route('dashboard.checkIns.show', ['uuid' => $checkIn->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('fields.showTruckColor', true)
            ->where('fields.showEmptyWeightLbs', false)
            ->where('fields.showDriversLicenseExpirationDate', false));
});

it('shows the language the driver checked in with', function (): void {
    $checkIn = CheckIn::factory()->create(['locale' => 'es']);

    $this->actingAs(allLocationsStaff())
        ->get(route('dashboard.checkIns.show', ['uuid' => $checkIn->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('checkIn.locale', 'es'));
});

it('shows an archived check-in', function (): void {
    $checkIn = CheckIn::factory()->create();
    $checkIn->delete();

    $this->actingAs(allLocationsStaff())
        ->get(route('dashboard.checkIns.show', ['uuid' => $checkIn->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('checkIn.uuid', $checkIn->uuid)
            ->whereNot('checkIn.archivedAt', null));
});

it('returns not found for a check-in that does not exist', function (): void {
    $this->actingAs(allLocationsStaff())
        ->get(route('dashboard.checkIns.show', ['uuid' => fake()->uuid()]))
        ->assertNotFound();
});
