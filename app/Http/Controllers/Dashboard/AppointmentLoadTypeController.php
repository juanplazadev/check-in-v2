<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\ChangeAppointmentLoadTypeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\AppointmentLoadTypeRequest;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Throwable;

/**
 * Changes an appointment's load type, and with it how long the booking holds
 * a dock. The lookup matches the detail page, so an appointment at a location
 * the user may not see is a 404, and so is an archived one.
 */
final class AppointmentLoadTypeController extends Controller
{
    /**
     * @throws Throwable
     */
    public function update(
        #[CurrentUser] User $user,
        string $uuid,
        AppointmentLoadTypeRequest $request,
        ChangeAppointmentLoadTypeAction $change,
    ): RedirectResponse {
        $appointment = Appointment::query()
            ->visibleTo($user)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $change->handle($appointment, $user, $request->loadTypeUuid());

        return back();
    }
}
