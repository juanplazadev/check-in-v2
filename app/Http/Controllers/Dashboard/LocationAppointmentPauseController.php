<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\PauseAppointmentsAction;
use App\Actions\ResumeAppointmentsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\LocationAppointmentPauseRequest;
use App\Http\Requests\Dashboard\LocationAppointmentResumeRequest;
use App\Models\Location;
use App\Models\User;
use App\Queries\LocationDirectory;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Throwable;

/**
 * Pauses and resumes new bookings at a location from its overview. The
 * location lookup matches the location's page, so one the user may not see
 * is a 404.
 */
final class LocationAppointmentPauseController extends Controller
{
    /**
     * @throws Throwable
     */
    public function store(
        #[CurrentUser] User $user,
        LocationAppointmentPauseRequest $request,
        PauseAppointmentsAction $pause,
    ): RedirectResponse {
        $location = $request->location;

        // The request already 404s an unseen location; this narrows the type.
        abort_unless($location instanceof Location, 404);

        $pause->handle($location, $user, (string) $request->validated('reason'), $request->duration());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => sprintf('Appointments at %s are paused.', $location->name),
        ]);

        return back();
    }

    /**
     * @throws Throwable
     */
    public function destroy(
        #[CurrentUser] User $user,
        string $uuid,
        LocationAppointmentResumeRequest $request,
        LocationDirectory $query,
        ResumeAppointmentsAction $resume,
    ): RedirectResponse {
        $location = $query->find($user, $uuid);

        $resumed = $resume->handle($location, $user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $resumed
                ? sprintf('Appointments at %s have resumed.', $location->name)
                : sprintf('Appointments at %s were not paused.', $location->name),
        ]);

        return back();
    }
}
