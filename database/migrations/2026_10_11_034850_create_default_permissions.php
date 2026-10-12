<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Permissions and their grants are reference data, like the roles: every
     * route checks one, and production never runs seeders. The grants come
     * from Role::permissions().
     */
    public function up(): void
    {
        foreach (Permission::cases() as $permission) {
            SpatiePermission::findOrCreate($permission->value, 'web');
        }

        foreach (Role::cases() as $role) {
            SpatieRole::findByName($role->value, 'web')->syncPermissions($role->permissions());
        }

        resolve(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        SpatiePermission::query()
            ->whereIn('name', array_column(Permission::cases(), 'value'))
            ->delete();

        resolve(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
