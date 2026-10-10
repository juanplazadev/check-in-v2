<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The staff who should hear about a record at a given location: verified,
 * and either holding an all-locations role or attached to that location.
 *
 * The bell notifications and the live dashboard feeds both use it, so who
 * hears about a record always matches who can open it.
 *
 * @see User::dashboardLocationIds()
 */
final readonly class DashboardAudience
{
    /**
     * @return Collection<int, User>
     */
    public function execute(int $locationId): Collection
    {
        return User::query()
            ->whereNotNull('email_verified_at')
            ->where(fn (Builder $query): Builder => $query
                ->role(Role::allLocations())
                ->orWhere(fn (Builder $query): Builder => $query
                    ->role(Role::locationScoped())
                    ->whereHas('locations', fn (Builder $query): Builder => $query->whereKey($locationId))))
            ->get();
    }
}
