<?php

declare(strict_types=1);

use App\Enums\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Roles are reference data, not seed data: registration assigns `driver`
     * and would throw if it were missing, and production never runs seeders.
     */
    public function up(): void
    {
        foreach (Role::cases() as $role) {
            SpatieRole::findOrCreate($role->value, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        SpatieRole::query()
            ->whereIn('name', array_column(Role::cases(), 'value'))
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
