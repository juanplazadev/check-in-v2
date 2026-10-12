<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\LinkCheckInAppointmentAction;
use App\Actions\UnlinkCheckInAppointmentAction;
use App\Dashboard\AppointmentLinkCandidates;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\CheckInAppointmentLinkRequest;
use App\Models\Appointment;
use App\Models\CheckIn;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Links a check-in to its booking from the detail page, or undoes a staff
 * link. The lookup matches the detail page, so a check-in at a location the
 * user may not see is a 404. Archived check-ins are a 404 too: they are
 * restored before anyone works them.
 */
final class CheckInAppointmentController extends Controller
{
    /**
     * The suggested bookings for the link dialog. Plain JSON rather than a
     * page prop, so the search only runs when someone opens the dialog.
     */
    public function index(#[CurrentUser] User $user, string $uuid, AppointmentLinkCandidates $candidates): JsonResponse
    {
        $checkIn = $this->checkIn($user, $uuid);

        return response()->json([
            'suggestions' => $checkIn->appointment_id === null ? $candidates->suggest($checkIn, $user) : [],
        ]);
    }

    /**
     * A booking that fails any rule gets the same message whether or not the
     * reference exists elsewhere, so the form cannot be used to probe codes.
     *
     * @throws Throwable
     */
    public function store(
        #[CurrentUser] User $user,
        string $uuid,
        CheckInAppointmentLinkRequest $request,
        AppointmentLinkCandidates $candidates,
        LinkCheckInAppointmentAction $link,
    ): RedirectResponse {
        $checkIn = $this->checkIn($user, $uuid);

        $referenceNumber = $request->referenceNumber();

        /** @var Appointment|null $appointment */
        $appointment = $candidates->linkable($checkIn, $user)
            ->when(
                $referenceNumber !== null,
                fn (Builder $query): Builder => $query->where('reference_number', $referenceNumber),
                fn (Builder $query): Builder => $query->where('uuid', $request->appointmentUuid()),
            )
            ->first();

        if ($appointment === null) {
            throw ValidationException::withMessages([
                $referenceNumber !== null ? 'reference_number' : 'appointment_uuid' => 'No linkable appointment with that reference at this location.',
            ]);
        }

        $link->handle($checkIn, $appointment, $user);

        return back();
    }

    /**
     * @throws Throwable
     */
    public function destroy(#[CurrentUser] User $user, string $uuid, UnlinkCheckInAppointmentAction $unlink): RedirectResponse
    {
        $unlink->handle($this->checkIn($user, $uuid), $user);

        return back();
    }

    private function checkIn(User $user, string $uuid): CheckIn
    {
        return CheckIn::query()
            ->visibleTo($user)
            ->where('uuid', $uuid)
            ->firstOrFail();
    }
}
