<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\CreateBuildingAction;
use App\Actions\DeleteBuildingAction;
use App\Actions\UpdateBuildingAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\LocationBuildingRequest;
use App\Models\Building;
use App\Models\Location;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Throwable;

/**
 * Adds, renames and removes the buildings a location's docks are grouped
 * under, from its docks page.
 */
final class LocationBuildingController extends Controller
{
    /**
     * @throws Throwable
     */
    public function store(#[CurrentUser] User $user, LocationBuildingRequest $request, CreateBuildingAction $create): RedirectResponse
    {
        // The request already 404s an unseen location; this narrows the type.
        abort_unless($request->location instanceof Location, 404);

        $building = $create->handle($request->location, $user, (string) $request->validated('name'));

        return $this->done(sprintf('%s added.', $building->name));
    }

    /**
     * @throws Throwable
     */
    public function update(#[CurrentUser] User $user, LocationBuildingRequest $request, UpdateBuildingAction $update): RedirectResponse
    {
        abort_unless($request->building instanceof Building, 404);

        $update->handle($request->building, $user, (string) $request->validated('name'));

        return $this->done('Building renamed.');
    }

    /**
     * @throws Throwable
     */
    public function destroy(#[CurrentUser] User $user, LocationBuildingRequest $request, DeleteBuildingAction $delete): RedirectResponse
    {
        abort_unless($request->building instanceof Building, 404);

        $delete->handle($request->building, $user);

        return $this->done(sprintf('%s removed. Its docks are now ungrouped.', $request->building->name));
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
