<?php

declare(strict_types=1);

namespace App\Queries;

use App\Dashboard\LocationRecordResolver;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The locations table and detail page: every location the viewer may see on
 * the dashboard (see User::dashboardLocationIds()), so location-scoped staff
 * get only the ones they are attached to. Not paginated, since there are only
 * a handful; the table searches on the client.
 *
 * Not to be confused with {@see DashboardLocations}, the location-scoped filter
 * options for the operational pages.
 */
final readonly class LocationDirectory
{
    /**
     * @return Collection<int, Location>
     */
    public function execute(User $viewer): Collection
    {
        return $this->visibleTo($viewer)
            ->with('address')
            ->withCount('users')
            ->orderBy('name')
            ->get();
    }

    public function find(User $viewer, string $uuid): Location
    {
        return $this->visibleTo($viewer)
            ->with(LocationRecordResolver::RELATIONS)
            ->withCount('users')
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    /**
     * Soft-deleted locations, most recently deleted first, for the restore
     * list. Unscoped: only admins may restore, and they see every location.
     *
     * @return Collection<int, Location>
     */
    public function deleted(): Collection
    {
        return Location::query()
            ->onlyTrashed()
            ->with('address')
            ->latest('deleted_at')
            ->get();
    }

    /**
     * @return Builder<Location>
     */
    private function visibleTo(User $viewer): Builder
    {
        $locationIds = $viewer->dashboardLocationIds();

        return Location::query()
            ->when($locationIds !== null, fn (Builder $query): Builder => $query->whereKey($locationIds));
    }
}
