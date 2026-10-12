<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role as SpatieRole;

/*
 * The data behind the read-only roles and permissions matrix. The gate is pinned in
 * DashboardPagesTest.
 */

it('lists every permission and what each role holds, as stored in the database', function (): void {
    SpatieRole::findByName(Role::Shipping->value)->revokePermissionTo(Permission::CompleteCheckIns);

    $expectedShipping = array_values(array_diff(
        array_column(Role::Shipping->permissions(), 'value'),
        [Permission::CompleteCheckIns->value],
    ));

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->get(route('dashboard.roles'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Dashboard/Roles/Index')
            ->where('permissions', array_column(Permission::cases(), 'value'))
            ->where('roles', function (Collection $roles) use ($expectedShipping): bool {
                $byName = $roles->keyBy('name');

                return $byName->keys()->sort()->values()->all() === collect(Role::cases())->map->value->sort()->values()->all()
                    && collect($byName['shipping']['permissions'])->sort()->values()->all() === collect($expectedShipping)->sort()->values()->all()
                    && count($byName['admin']['permissions']) === count(Permission::cases());
            }));
});
