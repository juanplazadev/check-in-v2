<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\DeleteAppointmentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\AppointmentDeleteRequest;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Throwable;

/**
 * Permanently deletes an appointment in any status, archived included, with a
 * required reason. The lookup matches the detail page, so an appointment at a
 * location the user may not see is a 404. The detail page is gone afterwards,
 * so this lands on the appointments list, with a toast confirming the delete.
 */
final class AppointmentDeleteController extends Controller
{
    /**
     * @throws Throwable
     */
    public function __invoke(
        #[CurrentUser] User $user,
        string $uuid,
        AppointmentDeleteRequest $request,
        DeleteAppointmentAction $delete,
    ): RedirectResponse {
        $appointment = Appointment::query()
            ->withTrashed()
            ->visibleTo($user)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $delete->handle($appointment, $user, $request->reason());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => sprintf('Appointment %s was permanently deleted.', $appointment->reference_number),
        ]);

        return to_route('dashboard.appointments');
    }
}
