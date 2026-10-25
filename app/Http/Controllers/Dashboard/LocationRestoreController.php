<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\RestoreLocationAction;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Throwable;

/**
 * Brings a deleted location back. A location that is not deleted is a 404.
 * Not scoped by location: only admins may restore, and they see every
 * location.
 */
final class LocationRestoreController extends Controller
{
    /**
     * @throws Throwable
     */
    public function __invoke(
        #[CurrentUser] User $user,
        string $uuid,
        RestoreLocationAction $restore,
    ): RedirectResponse {
        $location = Location::query()
            ->onlyTrashed()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $restore->handle($location, $user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => sprintf('%s was restored.', $location->name),
        ]);

        return back();
    }
}
