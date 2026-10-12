<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Enums\Role;
use App\Models\Location;
use App\Models\User;

/**
 * Shapes a location into the DashboardLocationSummary and DashboardLocation
 * contracts in resources/js/components/dashboard/types.ts, for the admin
 * locations table and detail page. Mapped by hand because id is hidden on the
 * model.
 *
 * Expects the users_count column that {@see \App\Queries\LocationDirectory} selects.
 */
final readonly class LocationRecordResolver
{
    /**
     * Everything {@see self::record()} reads, for eager loading at the call site.
     *
     * @var list<string>
     */
    public const array RELATIONS = ['address', 'users.roles'];

    /**
     * @return array<string, mixed>
     */
    public function summary(Location $location): array
    {
        return [
            'uuid' => $location->uuid,
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
            'staffCount' => (int) $location->getAttribute('users_count'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function record(Location $location): array
    {
        $location->loadMissing(self::RELATIONS);

        $address = $location->address;

        return [
            ...$this->summary($location),
            'email' => $location->email,
            'address' => [
                'street1' => $address->street1,
                'street2' => $address->street2,
                'city' => $address->city,
                'state' => $address->state,
                'zipCode' => $address->zip_code,
                'country' => $address->country,
            ],
            'maxDistanceAllowed' => $location->max_distance_allowed,
            'appointmentBooking' => [
                'maxDaysAhead' => $location->appointmentMaxBookingDaysAhead(),
                'slotIntervalMinutes' => $location->appointmentSlotIntervalMinutes(),
                'minLeadTimeMinutes' => $location->appointmentMinLeadTimeMinutes(),
                'bufferBeforeCloseMinutes' => $location->appointmentBufferBeforeCloseMinutes(),
            ],
            'createdAt' => $location->created_at->toIso8601String(),
            'staff' => $location->users
                ->sortBy('name')
                ->map($this->staffMember(...))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{uuid: string, name: string, email: string, roles: list<string>}
     */
    private function staffMember(User $user): array
    {
        $staffRoles = array_map(fn (Role $role): string => $role->value, Role::staff());

        return [
            'uuid' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
            // Staff roles only, as in UserRecordResolver.
            'roles' => array_values(array_intersect($staffRoles, $user->roles->pluck('name')->all())),
        ];
    }
}
