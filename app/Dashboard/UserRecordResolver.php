<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Location;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Shapes a user into the DashboardUser contract in
 * resources/js/components/dashboard/types.ts, for the admin users table and
 * detail page.
 *
 * Expects the last_activity, check_ins_count and appointments_count columns
 * that {@see \App\Queries\DashboardUsers} selects.
 */
final readonly class UserRecordResolver
{
    /**
     * Everything {@see self::record()} reads, for eager loading at the call site.
     *
     * @var list<string>
     */
    public const array RELATIONS = ['roles', 'locations.address', 'location.address'];

    /**
     * @return array<string, mixed>
     */
    public function record(User $user): array
    {
        $user->loadMissing(self::RELATIONS);

        /** @var list<string> $roleNames */
        $roleNames = $user->roles->pluck('name')->all();
        $staffRoles = array_map(fn (Role $role): string => $role->value, Role::staff());
        $lastActivity = $user->getAttribute('last_activity');

        return [
            'uuid' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
            'cellphone' => $user->cellphone,
            // Staff roles only: the page treats an empty list as a driver.
            'roles' => array_values(array_intersect($staffRoles, $roleNames)),
            'hasAllLocations' => $user->can(Permission::ViewAllLocations),
            'locations' => $user->locations
                ->sortBy('name')
                ->map($this->location(...))
                ->values()
                ->all(),
            'defaultLocation' => $user->location === null ? null : $this->location($user->location),
            'emailVerifiedAt' => $user->email_verified_at?->toIso8601String(),
            'createdAt' => $user->created_at->toIso8601String(),
            'lastActiveAt' => $lastActivity === null
                ? null
                : CarbonImmutable::createFromTimestamp((int) $lastActivity)->toIso8601String(),
            'checkInsCount' => (int) $user->getAttribute('check_ins_count'),
            'appointmentsCount' => (int) $user->getAttribute('appointments_count'),
        ];
    }

    /**
     * Mapped by hand because id is hidden on the model.
     *
     * @return array<string, mixed>
     */
    private function location(Location $location): array
    {
        return [
            'id' => $location->id,
            'name' => $location->name,
            'abbreviation' => $location->abbreviation,
            'city' => $location->address->city,
            'state' => $location->address->state,
            'timezone' => $location->timezone,
            'phone' => $location->phone,
            'phoneExt' => $location->phone_ext,
            'isActive' => $location->is_active,
            'isCheckInsEnabled' => $location->is_checkins_enabled,
            'isAppointmentsEnabled' => $location->is_appointments_enabled,
        ];
    }
}
