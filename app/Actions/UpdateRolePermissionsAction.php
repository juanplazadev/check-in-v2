<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\Permission;
use App\Enums\UserHistoryEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * Sets which permissions each given role grants. A role whose grants are
 * unchanged is left alone; each changed role is logged on the acting admin's
 * trail with what it gained and lost. Everyone holding a changed role feels
 * it on their next request.
 */
final readonly class UpdateRolePermissionsAction
{
    public function __construct(
        private RecordUserHistoryAction $recordUserHistory,
        private PermissionRegistrar $permissionRegistrar,
    ) {}

    /**
     * @param  array<string, list<Permission>>  $grants  The complete permission list for each role, keyed by role.
     * @return list<string> The roles whose grants changed.
     *
     * @throws Throwable
     */
    public function handle(User $actor, array $grants): array
    {
        $changed = DB::transaction(function () use ($actor, $grants): array {
            $changed = [];

            foreach ($grants as $roleName => $permissions) {
                $role = SpatieRole::findByName($roleName, 'web');

                $current = $role->permissions->pluck('name')->all();
                $requested = array_column($permissions, 'value');

                $granted = $this->inEnumOrder(array_diff($requested, $current));
                $revoked = $this->inEnumOrder(array_diff($current, $requested));

                if ($granted === [] && $revoked === []) {
                    continue;
                }

                $role->syncPermissions($requested);

                $this->recordUserHistory->handle($actor, UserHistoryEvent::RolePermissionsEdited, [
                    'role' => $roleName,
                    'granted' => $granted,
                    'revoked' => $revoked,
                ]);

                $changed[] = $roleName;
            }

            return $changed;
        });

        $this->permissionRegistrar->forgetCachedPermissions();

        return $changed;
    }

    /**
     * @param  array<array-key, string>  $names
     * @return list<string>
     */
    private function inEnumOrder(array $names): array
    {
        return array_values(array_intersect(array_column(Permission::cases(), 'value'), $names));
    }
}
