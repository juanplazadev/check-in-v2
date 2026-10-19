<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\AppointmentStatus;
use App\Enums\RecordHistoryEvent;
use App\Enums\UserHistoryEvent;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Sets a cancelled appointment back to scheduled, for a cancellation made by
 * mistake, and logs it on both the appointment's timeline and the staff
 * member's own account trail. The driver is not notified. Allowed whatever the
 * time slot, so a booking from earlier today can still be put back.
 *
 * The cancellation columns are cleared so the appointment drops out of
 * cancelled filters, sorting and exports. The reason and who cancelled it are
 * kept in the Reinstated timeline row's context, so the appointment still
 * shows why it was cancelled once it is back to scheduled.
 *
 * The status write also produces a StatusChanged timeline row through the
 * saved() hook in AppServiceProvider, and the updated() hook broadcasts the
 * change to open dashboards.
 */
final readonly class ReinstateAppointmentAction
{
    public function __construct(
        private RecordHistoryAction $recordHistory,
        private RecordUserHistoryAction $recordUserHistory,
    ) {}

    /**
     * @throws ValidationException when the appointment is not cancelled.
     * @throws Throwable
     */
    public function handle(Appointment $appointment, User $user): Appointment
    {
        return DB::transaction(function () use ($appointment, $user): Appointment {
            // Locked so two staff clicking at once cannot reinstate it twice.
            $appointment = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);

            if ($appointment->status !== AppointmentStatus::Cancelled) {
                throw ValidationException::withMessages([
                    'status' => 'This appointment is not cancelled.',
                ]);
            }

            $cancellation = [
                'cancelled_reason' => $appointment->cancelled_reason,
                'cancelled_by' => $appointment->cancelled_by,
            ];

            $appointment->forceFill([
                'status' => AppointmentStatus::Scheduled,
                'cancelled_at' => null,
                'cancelled_by' => null,
                'cancelled_reason' => null,
            ])->save();

            $this->recordHistory->handle($appointment, RecordHistoryEvent::Reinstated, user: $user, context: $cancellation);

            $this->recordUserHistory->handle($user, UserHistoryEvent::AppointmentReinstated, [
                'appointment' => $appointment->uuid,
            ]);

            return $appointment;
        });
    }
}
