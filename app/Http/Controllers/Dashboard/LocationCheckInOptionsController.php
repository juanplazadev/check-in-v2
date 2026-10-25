<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\UpdateLocationCheckInOptionsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\LocationCheckInOptionsUpdateRequest;
use App\Models\Location;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Throwable;

/**
 * Saves a location's check-in form fields or check-in notification emails,
 * one section of its check-in form & notifications page at a time. The
 * location lookup matches the location's page, so one the user may not see
 * is a 404.
 */
final class LocationCheckInOptionsController extends Controller
{
    /**
     * @throws Throwable
     */
    public function update(
        #[CurrentUser] User $user,
        LocationCheckInOptionsUpdateRequest $request,
        UpdateLocationCheckInOptionsAction $update,
    ): RedirectResponse {
        $location = $request->location;

        // The request already 404s an unseen location; this narrows the type.
        abort_unless($location instanceof Location, 404);

        $changes = $update->handle($location, $user, $request->formFields(), $request->emailAddresses());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $changes === []
                ? 'No changes to save.'
                : sprintf('Check-in settings for %s saved.', $location->name),
        ]);

        return back();
    }
}
