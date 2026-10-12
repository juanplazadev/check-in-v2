<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Read-only matrix of which role holds which permission, as stored in the
 * database (granted by the create_default_permissions migration). Both lists
 * are in insertion order, i.e. enum order.
 */
final class RolePermissionController extends Controller
{
    public function __invoke(): Response
    {
        return inertia('Dashboard/Roles/Index', [
            'permissions' => Permission::query()->orderBy('id')->pluck('name')->all(),
            'roles' => Role::query()
                ->with('permissions')
                ->orderBy('id')
                ->get()
                ->map(fn (Role $role): array => [
                    'name' => $role->name,
                    'permissions' => $role->permissions->sortBy('id')->pluck('name')->values()->all(),
                ])
                ->all(),
        ]);
    }
}
