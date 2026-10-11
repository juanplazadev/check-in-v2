<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\CheckIn;
use App\Models\User;

/**
 * The Feature test only pins the route-to-page wiring. These render the pages, so a crash in the
 * TanStack Table or Chart.js wiring (both sensitive to React Compiler memoization) surfaces here.
 */
beforeEach(function (): void {
    $this->actingAs(User::factory()->withRole(Role::Admin)->create());
});

it('renders the overview without console errors', function (): void {
    visit(route('dashboard'))
        ->assertSee('Overview')
        ->assertSee('Latest check-ins')
        ->assertSee('Recent activity')
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});

it('filters the check-ins table by status tab and search', function (): void {
    CheckIn::factory()->count(30)->create();

    $page = visit(route('dashboard.checkIns'))
        ->assertSee('All check-ins')
        ->assertSee('Showing 1 to 25 of 30 results');

    $page->click('button:has-text("Canceled")')
        ->assertDontSee('of 30 results')
        ->type('input[type="search"]', 'zzzz-no-match')
        ->assertSee('No check-ins match these filters.')
        ->assertNoJavascriptErrors();
});

it('hides user management from staff without view users', function (): void {
    $this->actingAs(User::factory()->withRole(Role::Sales)->create());

    visit(route('dashboard'))
        ->assertSee('Overview')
        ->assertSee('Locations')
        ->assertMissing('a[href$="/dashboard/users"]')
        ->assertNoJavascriptErrors();
});

it('lists users for admins', function (): void {
    visit(route('dashboard.users'))
        ->assertSee('All users')
        ->assertSee('Administration')
        ->assertNoJavascriptErrors();
});
