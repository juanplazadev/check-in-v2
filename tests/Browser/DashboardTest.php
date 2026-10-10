<?php

declare(strict_types=1);

use App\Enums\Role;
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
    $page = visit(route('dashboard.checkIns'))
        ->assertSee('All check-ins')
        ->assertSee('Showing 1 to 10 of 140 results');

    $page->click('Cancelled')
        ->assertDontSee('of 140 results')
        ->type('input[type="search"]', 'zzzz-no-match')
        ->assertSee('No check-ins match these filters.')
        ->assertNoJavascriptErrors();
});

it('hides user management from non-admin staff', function (): void {
    $this->actingAs(User::factory()->withRole(Role::Sales)->create());

    visit(route('dashboard'))
        ->assertSee('Overview')
        ->assertDontSee('Administration')
        ->assertNoJavascriptErrors();
});

it('lists users for admins', function (): void {
    visit(route('dashboard.users'))
        ->assertSee('All users')
        ->assertSee('Administration')
        ->assertNoJavascriptErrors();
});
