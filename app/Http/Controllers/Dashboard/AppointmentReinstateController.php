<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\ReinstateAppointmentAction;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Throwable;

/**
 * Sets a cancelled appointment back to scheduled. The lookup matches the
 * detail page, so an appointment at a location the user may not see is a 404.
 * Archived appointments are a 404 too: they are restored before anyone works
 * them.
 */
final class AppointmentReinstateController extends Controller
{
    /**
     * @throws Throwable
     */
    public function __invoke(
        #[CurrentUser] User $user,
        string $uuid,
        ReinstateAppointmentAction $reinstate,
    ): RedirectResponse {
        $appointment = Appointment::query()
            ->visibleTo($user)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $reinstate->handle($appointment, $user);

        return back();
    }
}
