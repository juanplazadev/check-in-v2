<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\RestoreAppointmentAction;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Throwable;

/**
 * Brings an archived appointment back. The lookup matches the detail page, so
 * an appointment at a location the user may not see is a 404. An appointment
 * that is not archived is a 404 too.
 */
final class AppointmentRestoreController extends Controller
{
    /**
     * @throws Throwable
     */
    public function __invoke(
        #[CurrentUser] User $user,
        string $uuid,
        RestoreAppointmentAction $restore,
    ): RedirectResponse {
        $appointment = Appointment::query()
            ->onlyTrashed()
            ->visibleTo($user)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $restore->handle($appointment, $user);

        return back();
    }
}
