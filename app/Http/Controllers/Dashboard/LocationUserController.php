<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\AttachLocationUsersAction;
use App\Actions\DetachLocationUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\LocationUserAttachRequest;
use App\Http\Requests\Dashboard\LocationUserDetachRequest;
use App\Models\User;
use App\Queries\LocationDirectory;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Throwable;

/**
 * Attaches staff to a location and detaches them (the location_user pivot).
 * The location lookup matches the location's page, so one the user may not
 * see is a 404.
 */
final class LocationUserController extends Controller
{
    /**
     * @throws Throwable
     */
    public function store(
        #[CurrentUser] User $user,
        string $uuid,
        LocationUserAttachRequest $request,
        LocationDirectory $query,
        AttachLocationUsersAction $attach,
    ): RedirectResponse {
        $location = $query->find($user, $uuid);

        $attached = $attach->handle($location, $request->users(), $user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => match (count($attached)) {
                0 => sprintf('Everyone selected was already at %s.', $location->name),
                1 => sprintf('%s was added to %s.', $attached[0]->name, $location->name),
                default => sprintf('%d users were added to %s.', count($attached), $location->name),
            },
        ]);

        return back();
    }

    /**
     * @throws Throwable
     */
    public function destroy(
        #[CurrentUser] User $user,
        string $uuid,
        string $userUuid,
        LocationUserDetachRequest $request,
        LocationDirectory $query,
        DetachLocationUserAction $detach,
    ): RedirectResponse {
        $location = $query->find($user, $uuid);
        $member = $location->users()->where('uuid', $userUuid)->firstOrFail();

        $detach->handle($location, $member, $user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => sprintf('%s was removed from %s.', $member->name, $location->name),
        ]);

        return back();
    }
}
