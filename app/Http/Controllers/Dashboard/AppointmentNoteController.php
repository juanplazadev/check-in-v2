<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\AddAppointmentNoteAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\AppointmentNoteRequest;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Throwable;

/**
 * Adds an internal note to an appointment. The lookup matches the detail page,
 * so an appointment at a location the user may not see is a 404, and archived
 * appointments still take notes.
 */
final class AppointmentNoteController extends Controller
{
    /**
     * @throws Throwable
     */
    public function __invoke(
        #[CurrentUser] User $user,
        string $uuid,
        AppointmentNoteRequest $request,
        AddAppointmentNoteAction $addNote,
    ): RedirectResponse {
        $appointment = Appointment::query()
            ->withTrashed()
            ->visibleTo($user)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $addNote->handle($appointment, $user, $request->body());

        return back();
    }
}
