<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\DeleteLocationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\LocationDeleteRequest;
use App\Models\Location;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Throwable;

/**
 * Soft-deletes a location and sends the admin back to the locations table,
 * since the location's own page no longer opens.
 */
final class LocationDeleteController extends Controller
{
    /**
     * @throws Throwable
     */
    public function __invoke(
        #[CurrentUser] User $user,
        LocationDeleteRequest $request,
        DeleteLocationAction $delete,
    ): RedirectResponse {
        $location = $request->location;

        // The request already 404s an unseen location; this narrows the type.
        abort_unless($location instanceof Location, 404);

        $delete->handle($location, $user, $request->reason());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => sprintf('%s was deleted.', $location->name),
        ]);

        return to_route('dashboard.locations');
    }
}
