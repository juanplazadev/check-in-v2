<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\EmailAppointmentPdfAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\AppointmentPdfEmailRequest;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Throwable;

/**
 * Emails an appointment's PDF to the driver. The lookup matches the detail page,
 * so an appointment at a location the user may not see is a 404, and archived
 * appointments can still be sent.
 */
final class AppointmentPdfEmailController extends Controller
{
    /**
     * @throws Throwable
     */
    public function __invoke(
        #[CurrentUser] User $user,
        string $uuid,
        AppointmentPdfEmailRequest $request,
        EmailAppointmentPdfAction $emailPdf,
    ): RedirectResponse {
        $appointment = Appointment::query()
            ->withTrashed()
            ->visibleTo($user)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $emailPdf->handle($appointment, $user);

        return back();
    }
}
