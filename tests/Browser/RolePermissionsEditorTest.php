<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * The matrix keeps its pending changes in the browser until they are reviewed,
 * so the toggle, the save bar and the review dialog are only reachable here.
 */
it('grants a permission through the review dialog', function (): void {
    $this->actingAs(User::factory()->withRole(Role::Admin)->create());

    visit('/dashboard/roles')
        ->assertSee('Roles & permissions')
        ->click('button[aria-label="Reopen check-ins for Shipping: not granted"]')
        ->assertSee('1 unsaved change')
        ->click('button:has-text("Review & save")')
        ->assertSee('Review permission changes')
        ->click('button:has-text("Save changes")')
        ->assertSee('Permissions for Shipping saved.')
        ->assertDontSee('1 unsaved change')
        ->assertNoJavascriptErrors();

    expect(SpatieRole::findByName(Role::Shipping->value)->hasPermissionTo(Permission::ReopenCheckIns))->toBeTrue();
});
