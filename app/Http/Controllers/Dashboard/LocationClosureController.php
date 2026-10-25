<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\AddLocationClosureAction;
use App\Actions\RemoveLocationClosureAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\LocationClosureDestroyRequest;
use App\Http\Requests\Dashboard\LocationClosureStoreRequest;
use App\Models\Location;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Throwable;

/**
 * Adds and removes a location's closures and special hours from its closures
 * page. The location lookup matches the location's page, so one the user may
 * not see is a 404.
 */
final class LocationClosureController extends Controller
{
    /**
     * @throws Throwable
     */
    public function store(
        #[CurrentUser] User $user,
        LocationClosureStoreRequest $request,
        AddLocationClosureAction $add,
    ): RedirectResponse {
        $location = $request->location;

        // The request already 404s an unseen location; this narrows the type.
        abort_unless($location instanceof Location, 404);

        $closure = $request->closure();

        $add->handle($location, $user, $request->features(), $closure);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => sprintf('Closure on %s added.', Date::parse($closure['override_date'])->format('M j')),
        ]);

        return back();
    }

    /**
     * @throws Throwable
     */
    public function destroy(
        #[CurrentUser] User $user,
        LocationClosureDestroyRequest $request,
        RemoveLocationClosureAction $remove,
    ): RedirectResponse {
        $location = $request->location;

        // The request already 404s an unseen location; this narrows the type.
        abort_unless($location instanceof Location, 404);

        $removed = $remove->handle($location, $user, $request->closureDate(), $request->features());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $removed === []
                ? 'No closure to remove.'
                : sprintf('Closure on %s removed.', Date::parse($request->closureDate())->format('M j')),
        ]);

        return back();
    }
}
