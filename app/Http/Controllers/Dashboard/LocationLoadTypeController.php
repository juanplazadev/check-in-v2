<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\CreateLoadTypeAction;
use App\Actions\DeleteLoadTypeAction;
use App\Actions\SetDefaultLoadTypeAction;
use App\Actions\UpdateLoadTypeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\LocationDefaultLoadTypeRequest;
use App\Http\Requests\Dashboard\LocationLoadTypeRequest;
use App\Models\LoadType;
use App\Models\Location;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Throwable;

/**
 * Adds, edits and removes a location's load types from its load types page.
 * The location lookup matches the location's page, so one the user may not
 * see is a 404.
 */
final class LocationLoadTypeController extends Controller
{
    /**
     * @throws Throwable
     */
    public function store(#[CurrentUser] User $user, LocationLoadTypeRequest $request, CreateLoadTypeAction $create): RedirectResponse
    {
        // The request already 404s an unseen location; this narrows the type.
        abort_unless($request->location instanceof Location, 404);

        $loadType = $create->handle($request->location, $user, $request->loadTypeAttributes());

        return $this->done(sprintf('%s added.', $loadType->name));
    }

    /**
     * @throws Throwable
     */
    public function update(#[CurrentUser] User $user, LocationLoadTypeRequest $request, UpdateLoadTypeAction $update): RedirectResponse
    {
        abort_unless($request->loadType instanceof LoadType, 404);

        $update->handle($request->loadType, $user, $request->loadTypeAttributes());

        return $this->done(sprintf('%s saved.', $request->loadType->name));
    }

    /**
     * @throws Throwable
     */
    public function destroy(#[CurrentUser] User $user, LocationLoadTypeRequest $request, DeleteLoadTypeAction $delete): RedirectResponse
    {
        abort_unless($request->loadType instanceof LoadType, 404);

        $name = $request->loadType->name;
        $delete->handle($request->loadType, $user);

        return $this->done(sprintf('%s removed.', $name));
    }

    /**
     * @throws Throwable
     */
    public function updateDefault(#[CurrentUser] User $user, LocationDefaultLoadTypeRequest $request, SetDefaultLoadTypeAction $setDefault): RedirectResponse
    {
        abort_unless($request->location instanceof Location, 404);

        $loadType = $request->defaultLoadType();
        $setDefault->handle($request->location, $user, $loadType);

        return $this->done($loadType instanceof LoadType ? 'Default load type saved.' : 'Drivers now choose the load type.');
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
