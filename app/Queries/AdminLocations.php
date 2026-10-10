<?php

declare(strict_types=1);

namespace App\Queries;

use App\Dashboard\LocationRecordResolver;
use App\Models\Location;
use Illuminate\Database\Eloquent\Collection;

/**
 * The admin locations table and detail page: every location, unscoped by the
 * admin's own attachments. Not paginated, since there are only a handful; the
 * table searches on the client.
 *
 * Not to be confused with {@see DashboardLocations}, the location-scoped filter
 * options for the operational pages.
 */
final readonly class AdminLocations
{
    /**
     * @return Collection<int, Location>
     */
    public function execute(): Collection
    {
        return Location::query()
            ->with('address')
            ->withCount('users')
            ->orderBy('name')
            ->get();
    }

    public function find(string $uuid): Location
    {
        return Location::query()
            ->with(LocationRecordResolver::RELATIONS)
            ->withCount('users')
            ->where('uuid', $uuid)
            ->firstOrFail();
    }
}
