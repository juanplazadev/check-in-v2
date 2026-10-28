<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\UserHistoryEvent;
use App\Models\User;
use App\Models\UserHistory;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role as SpatieRole;

/*
 * The roles and permissions matrix and the admin edit of it. The page gate is pinned in
 * DashboardPagesTest.
 */

/**
 * The shipping role's grants as stored, with the given changes applied.
 *
 * @param  list<Permission>  $grant
 * @param  list<Permission>  $revoke
 * @return list<string>
 */
function shippingGrantsWith(array $grant = [], array $revoke = []): array
{
    $current = SpatieRole::findByName(Role::Shipping->value)->permissions->pluck('name')->all();

    return array_values(array_diff(
        [...$current, ...array_column($grant, 'value')],
        array_column($revoke, 'value'),
    ));
}

it('lists every permission and what each role holds, as stored in the database', function (): void {
    SpatieRole::findByName(Role::Shipping->value)->revokePermissionTo(Permission::CompleteCheckIns);

    $expectedShipping = array_values(array_diff(
        array_column(Role::Shipping->permissions(), 'value'),
        [Permission::CompleteCheckIns->value],
    ));

    User::factory()->withRole(Role::Shipping)->count(2)->create();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->get(route('dashboard.roles'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Dashboard/Roles/Index')
            ->has('permissions', count(Permission::cases()))
            ->where('permissions.0', [
                'name' => 'view overview',
                'label' => 'View overview',
                'description' => 'Open the dashboard overview.',
                'group' => 'dashboard',
            ])
            ->where('assignable', fn (Collection $assignable): bool => $assignable->doesntContain(Permission::AccessDriverAccount->value)
                && $assignable->doesntContain(Permission::ViewErrors->value)
                && $assignable->doesntContain(Permission::ViewDeployments->value)
                && $assignable->doesntContain(Permission::ViewJobs->value)
                && $assignable->doesntContain(Permission::ManageJobs->value)
                && $assignable->doesntContain(Permission::ViewMessages->value)
                && $assignable->count() === count(Permission::cases()) - 6)
            ->where('roles', function (Collection $roles) use ($expectedShipping): bool {
                $byName = $roles->keyBy('name');

                return $roles->pluck('name')->all() === ['admin', 'shipping-manager', 'shipping', 'sales', 'driver']
                    && collect($byName['shipping']['permissions'])->sort()->values()->all() === collect($expectedShipping)->sort()->values()->all()
                    && $byName['shipping']['userCount'] === 2
                    && $byName['shipping']['editable'] === true
                    && $byName['admin']['editable'] === false
                    && $byName['driver']['editable'] === false
                    && count($byName['admin']['permissions']) === count(Permission::cases());
            }));
});

it('moves permissions between roles and logs each changed role on the admin trail', function (): void {
    $admin = User::factory()->withRole(Role::Admin)->create();
    $shippingUser = User::factory()->withRole(Role::Shipping)->create();

    $this->actingAs($admin)
        ->from(route('dashboard.roles'))
        ->patch(route('dashboard.roles.update'), [
            'roles' => [
                Role::Shipping->value => shippingGrantsWith(
                    grant: [Permission::ReopenCheckIns],
                    revoke: [Permission::CancelCheckIns],
                ),
                Role::Sales->value => array_column(Role::Sales->permissions(), 'value'),
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard.roles'))
        ->assertInertiaFlash('toast.message', 'Permissions for Shipping saved.');

    expect($shippingUser->fresh()->can(Permission::ReopenCheckIns))->toBeTrue()
        ->and($shippingUser->fresh()->can(Permission::CancelCheckIns))->toBeFalse();

    $trail = UserHistory::query()->sole();
    expect($trail->user_id)->toBe($admin->id)
        ->and($trail->event)->toBe(UserHistoryEvent::RolePermissionsEdited)
        ->and($trail->changes)->toBe([
            'role' => 'shipping',
            'granted' => ['reopen check-ins'],
            'revoked' => ['cancel check-ins'],
        ]);

    $this->get(route('dashboard.users.show', ['uuid' => $admin->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('history.data.0.description', 'Edited role permissions')
            ->where('history.data.0.details', 'Role: Shipping. Granted Reopen check-ins. Revoked Cancel check-ins'));
});

it('saves nothing and logs nothing when the grants are unchanged', function (): void {
    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->patch(route('dashboard.roles.update'), [
            'roles' => [Role::Shipping->value => shippingGrantsWith()],
        ])
        ->assertInertiaFlash('toast.message', 'No changes to save.');

    expect(UserHistory::query()->count())->toBe(0);
});

it('can take every permission from a role', function (): void {
    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->patch(route('dashboard.roles.update'), ['roles' => [Role::Sales->value => []]])
        ->assertSessionHasNoErrors();

    expect(SpatieRole::findByName(Role::Sales->value)->permissions)->toBeEmpty();
});

it('refuses to change admin or driver', function (Role $role): void {
    $before = SpatieRole::findByName($role->value)->permissions->pluck('name')->all();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->patch(route('dashboard.roles.update'), ['roles' => [$role->value => []]])
        ->assertSessionHasErrors(['roles' => 'Only the shipping manager, shipping and sales roles can be changed.']);

    expect(SpatieRole::findByName($role->value)->permissions->pluck('name')->all())->toBe($before);
})->with([
    'admin' => [Role::Admin],
    'driver' => [Role::Driver],
]);

it('refuses a permission the role cannot hold', function (string $permission): void {
    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->patch(route('dashboard.roles.update'), [
            'roles' => [Role::Shipping->value => [...shippingGrantsWith(), $permission]],
        ])
        ->assertSessionHasErrors(['roles.shipping.'.count(shippingGrantsWith()) => 'That permission cannot be granted to this role.']);

    expect(UserHistory::query()->count())->toBe(0);
})->with([
    'driver account' => [Permission::AccessDriverAccount->value],
    'errors' => [Permission::ViewErrors->value],
    'deployments' => [Permission::ViewDeployments->value],
    'jobs' => [Permission::ViewJobs->value],
    'manage jobs' => [Permission::ManageJobs->value],
    'messages' => [Permission::ViewMessages->value],
    'unknown' => ['launch rockets'],
]);

it('refuses fields outside the matrix', function (): void {
    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->patch(route('dashboard.roles.update'), [
            'roles' => [Role::Shipping->value => shippingGrantsWith()],
            'users' => 'someone',
        ])
        ->assertSessionHasErrors('users');
});

it('forbids staff without edit roles and permissions', function (): void {
    $this->actingAs(User::factory()->withRole(Role::ShippingManager)->create())
        ->patch(route('dashboard.roles.update'), ['roles' => [Role::Shipping->value => []]])
        ->assertForbidden();

    expect(SpatieRole::findByName(Role::Shipping->value)->permissions)->not->toBeEmpty();
});
