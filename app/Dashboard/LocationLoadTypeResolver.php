<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Enums\AppointmentStatus;
use App\Models\LoadType;
use App\Models\Location;
use Illuminate\Support\Facades\Date;

/**
 * Shapes a location's load types into the LocationLoadType contract in
 * resources/js/components/dashboard/types.ts. Mapped by hand because id is
 * hidden on the model.
 */
final readonly class LocationLoadTypeResolver
{
    /**
     * The load types page: every load type, active or not.
     *
     * @return list<array{id: string, name: string, direction: string, durationMinutes: int, isActive: bool, upcomingCount: int, hasRecords: bool}>
     */
    public function loadTypes(Location $location): array
    {
        return array_values($location->loadTypes()
            ->withCount([
                'appointments as upcoming_count' => fn ($query) => $query
                    ->withoutTrashed()
                    ->where('status', AppointmentStatus::Scheduled)
                    ->where('scheduled_for', '>=', Date::now()),
                'appointments as appointment_count',
            ])
            ->get()
            ->map(fn (LoadType $loadType): array => [
                'id' => $loadType->uuid,
                'name' => $loadType->name,
                'direction' => $loadType->direction->value,
                'durationMinutes' => $loadType->duration_minutes,
                'isActive' => $loadType->is_active,
                'upcomingCount' => (int) $loadType->getAttribute('upcoming_count'),
                'hasRecords' => (int) $loadType->getAttribute('appointment_count') > 0,
            ])
            ->all());
    }
}
