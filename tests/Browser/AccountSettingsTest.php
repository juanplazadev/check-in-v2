<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;

/**
 * The Feature test only asserts the Inertia payload. This one actually renders
 * the page, so a React crash in the new Radix controls, a missing translation
 * key or a bad prop shape surfaces here rather than in production.
 */
it('renders every settings section without console errors', function (): void {
    $driver = User::query()->create([
        'name' => 'John Driver',
        'email' => 'settings-browser@example.com',
        'password' => 'secret-password',
        'email_verified_at' => now(),
    ])->assignRole(Role::Driver);

    $this->actingAs($driver);

    $page = visit('/account/settings');

    $page->assertSee('Settings')
        ->assertSee('Preferences')
        ->assertSee('Notifications')
        ->assertSee('Security')
        ->assertSee('Delete account')
        // The three theme segments, including the System option that maps to a null column.
        ->assertSee('Light')
        ->assertSee('Dark')
        ->assertSee('System')
        ->assertSee('Usual location')
        // Both copy toggles, plus the footnote explaining what a text carries that an email does not.
        ->assertSee('Send me a copy of each check-in')
        ->assertSee('Send me a copy of each appointment booking')
        ->assertSee('The PDF receipt only comes by email')
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});

/**
 * The delete dialog is controlled and holds a form, rather than the plain
 * confirmation Radix ships. This renders it for real so a broken open/close
 * cycle or a missing translation key surfaces here.
 */
it('opens the delete dialog and asks for a password', function (): void {
    $driver = User::query()->create([
        'name' => 'John Driver',
        'email' => 'delete-browser@example.com',
        'password' => 'secret-password',
        'email_verified_at' => now(),
    ])->assignRole(Role::Driver);

    $this->actingAs($driver);

    visit('/account/settings')
        ->assertSee('Delete my account')
        // By test id, not text: the card heading reads "Delete account" too.
        ->click('@delete-account')
        ->assertSee('Delete your account?')
        ->assertSee('Enter your password to confirm')
        ->assertSee('Keep my account')
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});
