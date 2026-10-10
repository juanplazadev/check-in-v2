<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Location;

/**
 * Location filter options for the staff dashboard. Mapped by hand because id
 * is hidden on the model.
 */
final readonly class DashboardLocations
{
    /**
     * @return list<array{id: int, name: string}>
     */
    public function execute(): array
    {
        return array_values(Location::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Location $location): array => ['id' => $location->id, 'name' => $location->name])
            ->all());
    }
}
