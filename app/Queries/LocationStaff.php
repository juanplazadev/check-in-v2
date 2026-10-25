<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The staff behind a location's Users section: who is attached through the
 * location_user pivot, who sees it anyway through view all locations, and who
 * could still be attached.
 */
final readonly class LocationStaff
{
    public const int PER_PAGE = 25;

    /**
     * One page of the attached staff, by name, optionally narrowed to names or
     * emails containing the search. The id tiebreak keeps two people with the
     * same name from swapping between pages.
     *
     * @return LengthAwarePaginator<int, User>
     */
    public function attached(Location $location, ?string $search = null): LengthAwarePaginator
    {
        return User::query()
            ->whereHas('locations', fn (Builder $query): Builder => $query->whereKey($location->id))
            ->when($search, fn (Builder $query, string $search): Builder => $query->where(
                fn (Builder $query): Builder => $query
                    ->whereContains('name', $search)
                    ->orWhereContains('email', $search),
            ))
            ->with('roles')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * Staff who see every location whatever they are attached to, so the pivot
     * has no say over them.
     *
     * @return Collection<int, User>
     */
    public function allLocations(): Collection
    {
        return User::query()
            ->permission(Permission::ViewAllLocations->value)
            ->with('roles')
            ->orderBy('name')
            ->get();
    }

    /**
     * Location-scoped staff not yet attached to the location. Someone who also
     * holds view all locations through another role is left out: attaching
     * them would change nothing.
     *
     * @return Collection<int, User>
     */
    public function attachable(Location $location): Collection
    {
        return $this->locationScoped()
            ->whereDoesntHave('locations', fn (Builder $query): Builder => $query->whereKey($location->id))
            ->with('roles')
            ->orderBy('name')
            ->get()
            ->reject(fn (User $user): bool => $user->can(Permission::ViewAllLocations))
            ->values();
    }

    /**
     * Location-scoped staff who could be added to a location that does not
     * exist yet, optionally narrowed to the given uuids. Someone who also
     * holds view all locations is left out, as in attachable().
     *
     * @param  list<string>|null  $uuids
     * @return Collection<int, User>
     */
    public function assignable(?array $uuids = null): Collection
    {
        return $this->locationScoped()
            ->when($uuids !== null, fn (Builder $query): Builder => $query->whereIn('uuid', $uuids))
            ->with('roles')
            ->orderBy('name')
            ->get()
            ->reject(fn (User $user): bool => $user->can(Permission::ViewAllLocations))
            ->values();
    }

    /**
     * Users with a location-scoped role, for validating who may be attached.
     *
     * @return Builder<User>
     */
    public function locationScoped(): Builder
    {
        return User::query()->role(array_map(fn (Role $role): string => $role->value, Role::locationScoped()));
    }
}
