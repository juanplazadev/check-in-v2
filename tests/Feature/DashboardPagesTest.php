<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Appointment;
use App\Models\CheckIn;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\get;

/*
 * These pin the dashboard's route-to-page wiring and the gates in front of it: auth, verified, a
 * staff permission for every page, and view users, view roles and permissions, view errors, view deployments, view jobs or view messages (admins only) for the admin pages. Check-in data has its own tests in
 * DashboardCheckInsTest, the appointment detail in DashboardAppointmentsTest, users in
 * DashboardUsersTest, locations in DashboardLocationsTest; the other pages still render mock data. Location scoping is pinned in DashboardLocationScopeTest.
 */

function dashboardUser(Role $role = Role::Admin, bool $verified = true): User
{
    $factory = User::factory()->withRole($role);

    return ($verified ? $factory : $factory->unverified())->create();
}

$operationalPages = [
    'overview' => [fn (): string => route('dashboard'), 'Dashboard/Index'],
    'live queue' => [fn (): string => route('dashboard.queue'), 'Dashboard/Queue/Index'],
    'check-ins' => [fn (): string => route('dashboard.checkIns'), 'Dashboard/CheckIns/Index'],
    'check-in' => [fn (): string => route('dashboard.checkIns.show', ['uuid' => CheckIn::factory()->create()->uuid]), 'Dashboard/CheckIns/Show'],
    'appointments' => [fn (): string => route('dashboard.appointments'), 'Dashboard/Appointments/Index'],
    'appointment' => [fn (): string => route('dashboard.appointments.show', ['uuid' => Appointment::factory()->create()->uuid]), 'Dashboard/Appointments/Show'],
    'locations' => [fn (): string => route('dashboard.locations'), 'Dashboard/Locations/Index'],
    'location' => [fn (): string => route('dashboard.locations.show', ['uuid' => Location::factory()->create()->uuid]), 'Dashboard/Locations/Overview'],
];

$adminPages = [
    'users' => [fn (): string => route('dashboard.users'), 'Dashboard/Users/Index'],
    'user' => [fn (): string => route('dashboard.users.show', ['uuid' => User::factory()->create()->uuid]), 'Dashboard/Users/Show'],
    'roles' => [fn (): string => route('dashboard.roles'), 'Dashboard/Roles/Index'],
    'errors' => [fn (): string => route('dashboard.errors'), 'Dashboard/Errors/Index'],
    'error' => [fn (): string => route('dashboard.errors.show', ['issueId' => 123]), 'Dashboard/Errors/Show'],
    'deployments' => [fn (): string => route('dashboard.deployments'), 'Dashboard/Deployments/Index'],
    'jobs' => [fn (): string => route('dashboard.jobs'), 'Dashboard/Jobs/Index'],
    'messages' => [fn (): string => route('dashboard.messages'), 'Dashboard/Messages/Index'],
];

dataset('operational pages', $operationalPages);
dataset('admin pages', $adminPages);
// Not every staff role may see analytics; who may is pinned in DashboardAnalyticsTest.
$analyticsPage = ['analytics' => [fn (): string => route('dashboard.analytics'), 'Dashboard/Analytics/Index']];

dataset('dashboard pages', [...$operationalPages, ...$adminPages, ...$analyticsPage]);

dataset('non-admin staff', array_values(array_filter(Role::staff(), fn (Role $role): bool => $role !== Role::Admin)));

it('renders the operational pages for every staff role', function (Role $role, Closure $url, string $component): void {
    $target = $url();
    $user = dashboardUser($role);
    // Every location, so a location-scoped role is not 404'd on the detail pages.
    $user->locations()->attach(Location::query()->pluck('id'));

    $this->actingAs($user)
        ->get($target)
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component($component));
})->with(Role::staff())->with('operational pages');

it('renders the admin pages for admins', function (Closure $url, string $component): void {
    $this->actingAs(dashboardUser(Role::Admin))
        ->get($url())
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component($component));
})->with('admin pages');

it('forbids the admin pages to non-admin staff', function (Role $role, Closure $url): void {
    $this->actingAs(dashboardUser($role))
        ->get($url())
        ->assertForbidden();
})->with('non-admin staff')->with('admin pages');

it('forbids every dashboard page to drivers', function (Closure $url): void {
    $this->actingAs(dashboardUser(Role::Driver))
        ->get($url())
        ->assertForbidden();
})->with('dashboard pages');

it('redirects guests to the login page', function (Closure $url): void {
    get($url())->assertRedirect(route('login'));
})->with('dashboard pages');

it('sends unverified staff to the verification notice', function (Closure $url): void {
    $this->actingAs(dashboardUser(verified: false))
        ->get($url())
        ->assertRedirect(route('verification.notice'));
})->with('dashboard pages');

it("shares the signed-in user's roles and permissions with the page", function (): void {
    $this->actingAs(dashboardUser(Role::Sales))
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('auth.roles', [Role::Sales->value])
            ->where('auth.permissions', fn (Collection $permissions): bool => $permissions->sort()->values()->all()
                === collect(Role::Sales->permissions())->map->value->sort()->values()->all()));
});

it('rejects malformed record identifiers', function (string $path): void {
    $this->actingAs(dashboardUser())
        ->get($path)
        ->assertNotFound();
})->with([
    'check-in' => '/dashboard/check-ins/not-a-uuid',
    'appointment' => '/dashboard/appointments/not-a-uuid',
    'user' => '/dashboard/users/not-a-number',
    'location' => '/dashboard/locations/not-a-uuid',
    'error' => '/dashboard/errors/not-a-number',
]);
