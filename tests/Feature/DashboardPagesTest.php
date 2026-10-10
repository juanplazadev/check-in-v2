<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Appointment;
use App\Models\CheckIn;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\get;

/*
 * These pin the dashboard's route-to-page wiring and the gates in front of it: auth, verified, a
 * staff role for every page, and admin for user management. Check-in data has its own tests in
 * DashboardCheckInsTest, the appointment detail in DashboardAppointmentsTest; the other pages still
 * render mock data.
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
];

$userManagementPages = [
    'users' => [fn (): string => route('dashboard.users'), 'Dashboard/Users/Index'],
    'user' => [fn (): string => route('dashboard.users.show', ['id' => 1]), 'Dashboard/Users/Show'],
];

dataset('operational pages', $operationalPages);
dataset('user management pages', $userManagementPages);
dataset('dashboard pages', [...$operationalPages, ...$userManagementPages]);

dataset('non-admin staff', array_values(array_filter(Role::staff(), fn (Role $role): bool => $role !== Role::Admin)));

it('renders the operational pages for every staff role', function (Role $role, Closure $url, string $component): void {
    $this->actingAs(dashboardUser($role))
        ->get($url())
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component($component));
})->with(Role::staff())->with('operational pages');

it('renders user management for admins', function (Closure $url, string $component): void {
    $this->actingAs(dashboardUser(Role::Admin))
        ->get($url())
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component($component));
})->with('user management pages');

it('forbids user management to non-admin staff', function (Role $role, Closure $url): void {
    $this->actingAs(dashboardUser($role))
        ->get($url())
        ->assertForbidden();
})->with('non-admin staff')->with('user management pages');

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

it('shares the signed-in user\'s roles with the page', function (): void {
    $this->actingAs(dashboardUser(Role::Sales))
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('auth.roles', [Role::Sales->value]));
});

it('rejects malformed record identifiers', function (string $path): void {
    $this->actingAs(dashboardUser())
        ->get($path)
        ->assertNotFound();
})->with([
    'check-in' => '/dashboard/check-ins/not-a-uuid',
    'appointment' => '/dashboard/appointments/not-a-uuid',
    'user' => '/dashboard/users/not-a-number',
]);
