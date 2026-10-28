<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\AssignAppointmentDockAction;
use App\Actions\UnassignAppointmentDockAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\AppointmentDockRequest;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Throwable;

/**
 * Places an appointment on a dock, moves it, or takes it off. The lookup
 * matches the detail page, so an appointment at a location the user may not
 * see is a 404, and so is an archived one.
 */
final class AppointmentDockController extends Controller
{
    /**
     * @throws Throwable
     */
    public function update(
        #[CurrentUser] User $user,
        string $uuid,
        AppointmentDockRequest $request,
        AssignAppointmentDockAction $assign,
    ): RedirectResponse {
        $assign->handle($this->find($user, $uuid), $user, $request->dockUuid());

        return back();
    }

    /**
     * @throws Throwable
     */
    public function destroy(#[CurrentUser] User $user, string $uuid, UnassignAppointmentDockAction $unassign): RedirectResponse
    {
        $unassign->handle($this->find($user, $uuid), $user);

        return back();
    }

    private function find(User $user, string $uuid): Appointment
    {
        return Appointment::query()
            ->visibleTo($user)
            ->where('uuid', $uuid)
            ->firstOrFail();
    }
}
