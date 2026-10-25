<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\UpdateLocationAppointmentOptionsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\LocationAppointmentOptionsUpdateRequest;
use App\Models\Location;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Throwable;

/**
 * Saves a location's appointment booking rules or booking notification
 * emails, one section of its booking & notifications page at a time. The
 * location lookup matches the location's page, so one the user may not see
 * is a 404.
 */
final class LocationAppointmentOptionsController extends Controller
{
    /**
     * @throws Throwable
     */
    public function update(
        #[CurrentUser] User $user,
        LocationAppointmentOptionsUpdateRequest $request,
        UpdateLocationAppointmentOptionsAction $update,
    ): RedirectResponse {
        $location = $request->location;

        // The request already 404s an unseen location; this narrows the type.
        abort_unless($location instanceof Location, 404);

        $changes = $update->handle($location, $user, $request->bookingSettings(), $request->emailAddresses());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $changes === []
                ? 'No changes to save.'
                : sprintf('Appointment settings for %s saved.', $location->name),
        ]);

        return back();
    }
}
