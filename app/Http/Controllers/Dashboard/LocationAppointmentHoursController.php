<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\UpdateLocationHoursAction;
use App\Enums\ScheduleFeature;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\LocationHoursUpdateRequest;
use App\Models\Location;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Throwable;

/**
 * Saves a location's weekly appointment hours from its appointment hours page. The
 * location lookup matches the location's page, so one the user may not see
 * is a 404.
 */
final class LocationAppointmentHoursController extends Controller
{
    /**
     * @throws Throwable
     */
    public function update(
        #[CurrentUser] User $user,
        LocationHoursUpdateRequest $request,
        UpdateLocationHoursAction $update,
    ): RedirectResponse {
        $location = $request->location;

        // The request already 404s an unseen location; this narrows the type.
        abort_unless($location instanceof Location, 404);

        $changes = $update->handle($location, $user, ScheduleFeature::Appointments, $request->days());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $changes === []
                ? 'No changes to save.'
                : sprintf('%s hours for %s saved.', ScheduleFeature::Appointments->label(), $location->name),
        ]);

        return back();
    }
}
