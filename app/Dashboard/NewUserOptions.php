<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Location;
use App\Models\User;
use App\Queries\LocationDirectory;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * The choices behind the add user dialog: the staff roles, each marked by
 * whether it sees every location, and the locations a location-scoped user
 * can be attached to.
 *
 * Whether a role sees every location is read from its live grants, since
 * admins can move view all locations between roles.
 */
final readonly class NewUserOptions
{
    public function __construct(private LocationDirectory $locations) {}

    /**
     * @return list<array{value: string, seesAllLocations: bool}>
     */
    public function roles(): array
    {
        $seesAll = SpatieRole::query()
            ->whereIn('name', array_map(fn (Role $role): string => $role->value, Role::staff()))
            ->with('permissions')
            ->get()
            ->mapWithKeys(fn (SpatieRole $role): array => [
                $role->name => $role->permissions->contains('name', Permission::ViewAllLocations->value),
            ]);

        return array_map(fn (Role $role): array => [
            'value' => $role->value,
            'seesAllLocations' => (bool) $seesAll->get($role->value, false),
        ], Role::staff());
    }

    public function seesAllLocations(Role $role): bool
    {
        return SpatieRole::findByName($role->value)->hasPermissionTo(Permission::ViewAllLocations->value);
    }

    /**
     * @return list<array{uuid: string, name: string, city: string}>
     */
    public function locations(User $viewer): array
    {
        return array_values($this->locations->execute($viewer)
            ->map(fn (Location $location): array => [
                'uuid' => $location->uuid,
                'name' => $location->name,
                'city' => $location->address->city,
            ])
            ->all());
    }
}
