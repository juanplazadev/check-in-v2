<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\ArchiveAppointmentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\AppointmentArchiveRequest;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Throwable;

/**
 * Archives an appointment that is no longer scheduled, with an optional
 * reason. The lookup matches the detail page, so an appointment at a location
 * the user may not see is a 404. An already archived appointment is a 404 too.
 */
final class AppointmentArchiveController extends Controller
{
    /**
     * @throws Throwable
     */
    public function __invoke(
        #[CurrentUser] User $user,
        string $uuid,
        AppointmentArchiveRequest $request,
        ArchiveAppointmentAction $archive,
    ): RedirectResponse {
        $appointment = Appointment::query()
            ->visibleTo($user)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $archive->handle($appointment, $user, $request->reason());

        return back();
    }
}
