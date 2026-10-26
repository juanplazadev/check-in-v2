<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

/**
 * The add user dialog reveals the locations step only for a location-scoped
 * role and keeps its success state open, so both are only reachable here.
 */
it('adds a location-scoped user through the dialog', function (): void {
    Notification::fake();
    Location::factory()->create(['name' => 'Alpha Dock']);
    $this->actingAs(User::factory()->withRole(Role::Admin)->create());

    visit('/dashboard/users')
        ->click('button:has-text("Add user")')
        ->assertSee('Create a staff account')
        ->fill('#location_name', 'Rosa Diaz')
        ->fill('#location_email', 'rosa@example.com')
        ->assertDontSee('Alpha Dock')
        ->click('button[role="radio"]:has-text("Shipping manager")')
        ->assertSee('Alpha Dock')
        ->click('button[role="checkbox"]:has-text("Alpha Dock")')
        ->click('button:has-text("Add and send invite")')
        ->assertSee('Rosa Diaz was added')
        ->assertSee('An invite is on its way to rosa@example.com.')
        ->assertNoJavascriptErrors();

    $user = User::query()->where('email', 'rosa@example.com')->sole();

    expect($user->hasRole(Role::ShippingManager))->toBeTrue()
        ->and($user->locations()->pluck('name')->all())->toBe(['Alpha Dock']);
});
