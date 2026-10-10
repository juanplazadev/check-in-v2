<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('renders the login page for guests', function (): void {
    get(route('login'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Auth/Login')
            ->has('translations.login.title')
            ->has('translations.login.signIn'));
});

it('sends a signed-in driver from the login page to their account', function (): void {
    $this->actingAs(User::factory()->withRole(Role::Driver)->create())
        ->get(route('login'))
        ->assertRedirect(route('account'));
});

it('sends signed-in staff from the login page to the dashboard', function (Role $role): void {
    $this->actingAs(User::factory()->withRole($role)->create())
        ->get(route('login'))
        ->assertRedirect(route('dashboard'));
})->with(Role::staff());

it('sends a driver to their account after logging in', function (): void {
    $driver = User::factory()->withRole(Role::Driver)->create();

    post('/login', [
        'email' => $driver->email,
        'password' => 'password',
    ])->assertRedirect(route('account'));
});

it('sends staff to the dashboard after logging in', function (Role $role): void {
    $employee = User::factory()->withRole($role)->create();

    post('/login', [
        'email' => $employee->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));
})->with(Role::staff());
