<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\PauseCheckInsAction;
use App\Actions\ResumeCheckInsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\LocationCheckInPauseRequest;
use App\Http\Requests\Dashboard\LocationCheckInResumeRequest;
use App\Models\Location;
use App\Models\User;
use App\Queries\LocationDirectory;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Throwable;

/**
 * Pauses and resumes new check-ins at a location from its overview. The
 * location lookup matches the location's page, so one the user may not see
 * is a 404.
 */
final class LocationCheckInPauseController extends Controller
{
    /**
     * @throws Throwable
     */
    public function store(
        #[CurrentUser] User $user,
        LocationCheckInPauseRequest $request,
        PauseCheckInsAction $pause,
    ): RedirectResponse {
        $location = $request->location;

        // The request already 404s an unseen location; this narrows the type.
        abort_unless($location instanceof Location, 404);

        $pause->handle($location, $user, (string) $request->validated('reason'), $request->duration());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => sprintf('Check-ins at %s are paused.', $location->name),
        ]);

        return back();
    }

    /**
     * @throws Throwable
     */
    public function destroy(
        #[CurrentUser] User $user,
        string $uuid,
        LocationCheckInResumeRequest $request,
        LocationDirectory $query,
        ResumeCheckInsAction $resume,
    ): RedirectResponse {
        $location = $query->find($user, $uuid);

        $resumed = $resume->handle($location, $user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $resumed
                ? sprintf('Check-ins at %s have resumed.', $location->name)
                : sprintf('Check-ins at %s were not paused.', $location->name),
        ]);

        return back();
    }
}
