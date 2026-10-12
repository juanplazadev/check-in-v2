<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Narrows a location-owned record to the locations a staff member may see.
 * Every staff dashboard read of check-ins and appointments goes through it.
 *
 * @see User::dashboardLocationIds()
 */
trait VisibleOnDashboard
{
    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $locationIds = $user->dashboardLocationIds();

        if ($locationIds !== null) {
            $query->whereIn($query->qualifyColumn('location_id'), $locationIds);
        }
    }
}
