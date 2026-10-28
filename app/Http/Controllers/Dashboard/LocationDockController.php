<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\CreateDockAction;
use App\Actions\DeleteDockAction;
use App\Actions\UpdateDockAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\LocationDockRequest;
use App\Models\Dock;
use App\Models\Location;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Throwable;

/**
 * Adds, edits and removes a location's docks from its docks page. The
 * location lookup matches the location's page, so one the user may not see
 * is a 404.
 */
final class LocationDockController extends Controller
{
    /**
     * @throws Throwable
     */
    public function store(#[CurrentUser] User $user, LocationDockRequest $request, CreateDockAction $create): RedirectResponse
    {
        // The request already 404s an unseen location; this narrows the type.
        abort_unless($request->location instanceof Location, 404);

        $dock = $create->handle($request->location, $user, $request->dockAttributes());

        return $this->done(sprintf('%s added.', $dock->name));
    }

    /**
     * @throws Throwable
     */
    public function update(#[CurrentUser] User $user, LocationDockRequest $request, UpdateDockAction $update): RedirectResponse
    {
        abort_unless($request->dock instanceof Dock, 404);

        $update->handle($request->dock, $user, $request->dockAttributes());

        return $this->done(sprintf('%s saved.', $request->dock->name));
    }

    /**
     * @throws Throwable
     */
    public function destroy(#[CurrentUser] User $user, LocationDockRequest $request, DeleteDockAction $delete): RedirectResponse
    {
        abort_unless($request->dock instanceof Dock, 404);

        $delete->handle($request->dock, $user);

        return $this->done(sprintf('%s removed.', $request->dock->name));
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
