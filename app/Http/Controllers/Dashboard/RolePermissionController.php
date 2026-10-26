<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\UpdateRolePermissionsAction;
use App\Enums\Permission;
use App\Enums\PermissionGroup;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\RolePermissionsUpdateRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role as SpatieRole;
use Throwable;

/**
 * The matrix of which role holds which permission, as stored in the
 * database, and the admin edit of it. Only the editable roles (see
 * Role::editable()) can change; admin and driver are shown locked.
 * Permissions are listed in enum order; roles staff first, then driver.
 */
final class RolePermissionController extends Controller
{
    public function index(): Response
    {
        // Staff from most to least access, then driver.
        $roleOrder = array_flip(array_column([...Role::staff(), Role::Driver], 'value'));

        return inertia('Dashboard/Roles/Index', [
            'groups' => array_map(fn (PermissionGroup $group): array => [
                'key' => $group->value,
                'label' => $group->label(),
            ], PermissionGroup::cases()),
            'permissions' => array_map(fn (Permission $permission): array => [
                'name' => $permission->value,
                'label' => $permission->label(),
                'description' => $permission->description(),
                'group' => $permission->group()->value,
            ], Permission::cases()),
            'roles' => SpatieRole::query()
                ->with('permissions')
                ->withCount('users')
                ->get()
                ->sortBy(fn (SpatieRole $role): int => $roleOrder[$role->name] ?? PHP_INT_MAX)
                ->map(fn (SpatieRole $role): array => [
                    'name' => $role->name,
                    'editable' => Role::tryFrom($role->name)?->isEditable() ?? false,
                    'userCount' => (int) $role->getAttribute('users_count'),
                    'permissions' => $role->permissions->sortBy('id')->pluck('name')->values()->all(),
                ])
                ->values()
                ->all(),
            'assignable' => array_column(Role::assignablePermissions(), 'value'),
        ]);
    }

    /**
     * @throws Throwable
     */
    public function update(
        #[CurrentUser] User $user,
        RolePermissionsUpdateRequest $request,
        UpdateRolePermissionsAction $update,
    ): RedirectResponse {
        $changed = $update->handle($user, $request->grants());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => match (count($changed)) {
                0 => 'No changes to save.',
                1 => sprintf('Permissions for %s saved.', Str::ucfirst(str_replace('-', ' ', $changed[0]))),
                default => sprintf('Permissions for %d roles saved.', count($changed)),
            },
        ]);

        return back();
    }
}
