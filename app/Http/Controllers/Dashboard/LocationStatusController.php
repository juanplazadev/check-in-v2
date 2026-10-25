<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\UpdateLocationDetailsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\LocationStatusUpdateRequest;
use App\Models\Location;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Throwable;

/**
 * Turns a location, or its check-ins or appointments, on or off. Logged like
 * any other details edit. Drivers and guests stop seeing what is off at once;
 * existing check-ins and appointments are kept.
 */
final class LocationStatusController extends Controller
{
    /**
     * @throws Throwable
     */
    public function update(
        #[CurrentUser] User $user,
        LocationStatusUpdateRequest $request,
        UpdateLocationDetailsAction $update,
    ): RedirectResponse {
        $location = $request->location;

        // The request already 404s an unseen location; this narrows the type.
        abort_unless($location instanceof Location, 404);

        $flags = $request->flags();
        $changes = $update->handle($location, $user, $flags);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $changes === [] ? 'Nothing changed.' : $this->message($location, $flags),
        ]);

        return back();
    }

    /**
     * @param  array<string, bool>  $flags
     */
    private function message(Location $location, array $flags): string
    {
        if (count($flags) !== 1) {
            return sprintf('%s was updated.', $location->name);
        }

        $enabled = reset($flags);

        return match (key($flags)) {
            'is_active' => sprintf('%s is now %s.', $location->name, $enabled ? 'active' : 'inactive'),
            'is_checkins_enabled' => sprintf('Check-ins at %s are turned %s.', $location->name, $enabled ? 'on' : 'off'),
            default => sprintf('Appointments at %s are turned %s.', $location->name, $enabled ? 'on' : 'off'),
        };
    }
}
