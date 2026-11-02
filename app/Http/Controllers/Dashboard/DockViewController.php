<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Dashboard\DockViewResolver;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\DockViewRequest;
use App\Models\Location;
use App\Models\User;
use App\Queries\DashboardLocations;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Support\Facades\Date;
use Inertia\Response;

/**
 * One location's day: docks as columns, time as rows. Opens on the user's first
 * location and that location's today. A location the user may not see is a 404.
 */
final class DockViewController extends Controller
{
    public function __invoke(
        #[CurrentUser] User $user,
        DockViewRequest $request,
        DashboardLocations $locations,
        DockViewResolver $resolver,
    ): Response {
        $options = $locations->execute($user);
        $locationId = $request->locationId() ?? ($options[0]['id'] ?? null);

        if ($locationId === null) {
            return inertia('Dashboard/Appointments/DockView', [
                'locations' => [],
                'locationId' => null,
                'day' => null,
                'docks' => [],
                'appointments' => [],
            ]);
        }

        abort_unless(in_array($locationId, array_column($options, 'id'), true), 404);

        $location = Location::query()->findOrFail($locationId);
        $date = $request->viewedDate() ?? Date::now($location->timezone)->toDateString();

        return inertia('Dashboard/Appointments/DockView', [
            'locations' => $options,
            'locationId' => $locationId,
            'day' => fn (): array => $resolver->day($location, $date),
            'docks' => fn (): array => $resolver->columns($location, $date),
            'appointments' => fn (): array => $resolver->appointments($location, $date),
        ]);
    }
}
