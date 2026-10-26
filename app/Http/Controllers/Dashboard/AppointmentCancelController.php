<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\StaffCancelAppointmentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\AppointmentCancelRequest;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Throwable;

/**
 * Cancels a scheduled appointment with a reason. The lookup matches the detail
 * page, so an appointment at a location the user may not see is a 404.
 * Archived appointments are a 404 too: they are restored before anyone works
 * them.
 */
final class AppointmentCancelController extends Controller
{
    /**
     * @throws Throwable
     */
    public function __invoke(
        #[CurrentUser] User $user,
        string $uuid,
        AppointmentCancelRequest $request,
        StaffCancelAppointmentAction $cancel,
    ): RedirectResponse {
        $appointment = Appointment::query()
            ->visibleTo($user)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $cancel->handle($appointment, $user, $request->reason());

        return back();
    }
}
