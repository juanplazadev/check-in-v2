<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\AssignCheckInDockWithNoticeAction;
use App\Actions\SendDoorAssignmentAction;
use App\Actions\UnassignCheckInDockAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\CheckInDockRequest;
use App\Models\CheckIn;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Throwable;

/**
 * Sends a waiting truck to a door, takes it off, or texts the driver their
 * door again. The lookup matches the detail page, so a check-in at a
 * location the user may not see is a 404, and so is an archived one.
 */
final class CheckInDockController extends Controller
{
    /**
     * @throws Throwable
     */
    public function update(
        #[CurrentUser] User $user,
        string $uuid,
        CheckInDockRequest $request,
        AssignCheckInDockWithNoticeAction $assign,
    ): RedirectResponse {
        $assign->handle($this->find($user, $uuid), $user, $request->dockUuid(), $request->shouldNotify());

        return back();
    }

    /**
     * @throws Throwable
     */
    public function destroy(#[CurrentUser] User $user, string $uuid, UnassignCheckInDockAction $unassign): RedirectResponse
    {
        $unassign->handle($this->find($user, $uuid), $user);

        return back();
    }

    public function notify(#[CurrentUser] User $user, string $uuid, SendDoorAssignmentAction $send): RedirectResponse
    {
        $send->handle($this->find($user, $uuid));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Door texted to the driver.']);

        return back();
    }

    private function find(User $user, string $uuid): CheckIn
    {
        return CheckIn::query()
            ->visibleTo($user)
            ->where('uuid', $uuid)
            ->firstOrFail();
    }
}
