<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Location filter options for the staff dashboard: only the locations the user
 * may see. Mapped by hand because id is hidden on the model.
 */
final readonly class DashboardLocations
{
    /**
     * @return list<array{id: int, name: string}>
     */
    public function execute(User $user): array
    {
        $locationIds = $user->dashboardLocationIds();

        return array_values(Location::query()
            ->when($locationIds !== null, fn (Builder $query): Builder => $query->whereKey($locationIds))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Location $location): array => ['id' => $location->id, 'name' => $location->name])
            ->all());
    }
}
