<?php

declare(strict_types=1);

namespace App\Actions;

use App\Dashboard\AppointmentLinkCandidates;
use App\Enums\AppointmentLinkMethod;
use App\Enums\AppointmentStatus;
use App\Enums\RecordHistoryEvent;
use App\Enums\UserHistoryEvent;
use App\Events\CheckInUpdated;
use App\Models\Appointment;
use App\Models\CheckIn;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Ties a check-in a driver made on their own to the booking it fulfils, on
 * behalf of a staff member, and logs it on both records' timelines and the
 * staff member's own account trail.
 *
 * The booking moves to checked-in; that status write records its own
 * timeline row and broadcasts through the hooks in AppServiceProvider. The
 * check-in's status is untouched, so CheckInUpdated is dispatched here.
 */
final readonly class LinkCheckInAppointmentAction
{
    public function __construct(
        private AppointmentLinkCandidates $candidates,
        private RecordHistoryAction $recordHistory,
        private RecordUserHistoryAction $recordUserHistory,
    ) {}

    /**
     * @throws ValidationException when the check-in is already linked or the booking no longer linkable.
     * @throws Throwable
     */
    public function handle(CheckIn $checkIn, Appointment $appointment, User $user): CheckIn
    {
        return DB::transaction(function () use ($checkIn, $appointment, $user): CheckIn {
            // Both locked so two staff linking at once cannot double-book either side.
            $checkIn = CheckIn::query()->lockForUpdate()->findOrFail($checkIn->id);

            if ($checkIn->appointment_id !== null) {
                throw ValidationException::withMessages([
                    'appointment' => 'This check-in is already linked to an appointment.',
                ]);
            }

            $appointment = $this->candidates->linkable($checkIn, $user)
                ->lockForUpdate()
                ->whereKey($appointment->id)
                ->first();

            if ($appointment === null) {
                throw ValidationException::withMessages([
                    'appointment' => 'That appointment can no longer be linked.',
                ]);
            }

            $checkIn->forceFill([
                'appointment_id' => $appointment->id,
                'appointment_linked_at' => Date::now(),
                'appointment_linked_via' => AppointmentLinkMethod::Staff,
                'appointment_linked_by' => $user->id,
            ])->save();

            $appointment->forceFill(['status' => AppointmentStatus::CheckedIn])->save();

            $this->recordHistory->handle($checkIn, RecordHistoryEvent::AppointmentLinked, user: $user, context: [
                'reference_number' => $appointment->reference_number,
            ]);

            $this->recordHistory->handle($appointment, RecordHistoryEvent::AppointmentLinked, user: $user, context: [
                'reference_number' => $checkIn->reference_number,
            ]);

            $this->recordUserHistory->handle($user, UserHistoryEvent::CheckInAppointmentLinked, [
                'check_in' => $checkIn->uuid,
                'appointment' => $appointment->uuid,
            ]);

            event(new CheckInUpdated($checkIn));

            return $checkIn;
        });
    }
}
