<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\UpdateLocationDetailsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\LocationDetailsUpdateRequest;
use App\Models\Location;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Throwable;

/**
 * Edits a location's details, one section of the Details page at a time. The
 * request's lookup matches the location's page, so one the user may not see
 * is a 404.
 */
final class LocationDetailsController extends Controller
{
    /**
     * @throws Throwable
     */
    public function update(
        #[CurrentUser] User $user,
        LocationDetailsUpdateRequest $request,
        UpdateLocationDetailsAction $update,
    ): RedirectResponse {
        $location = $request->location;

        // The request already 404s an unseen location; this narrows the type.
        abort_unless($location instanceof Location, 404);

        $changes = $update->handle($location, $user, $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $changes === []
                ? 'Nothing changed.'
                : sprintf('%s was updated.', $request->validated('name', $location->name)),
        ]);

        return back();
    }
}
