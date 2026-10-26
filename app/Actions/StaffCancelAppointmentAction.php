<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\AppointmentStatus;
use App\Enums\UserHistoryEvent;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Cancels a scheduled appointment on behalf of a staff member, keeping who did
 * it and why, and logs it on the staff member's own account trail. Neither the
 * driver nor the dock is notified, unlike {@see CancelAppointmentAction}, which
 * is the driver cancelling their own booking. The day-before reminder stops on
 * its own: only scheduled bookings are picked up for it.
 *
 * The status write is what produces the appointment's timeline row: the
 * saved() hook in AppServiceProvider records a StatusChanged row, credited to
 * the signed-in user, and the updated() hook broadcasts the change to open
 * dashboards. Nothing is written to the appointment trail by hand here.
 */
final readonly class StaffCancelAppointmentAction
{
    public function __construct(private RecordUserHistoryAction $recordUserHistory) {}

    /**
     * @throws ValidationException when the appointment is no longer scheduled.
     * @throws Throwable
     */
    public function handle(Appointment $appointment, User $user, string $reason): Appointment
    {
        return DB::transaction(function () use ($appointment, $user, $reason): Appointment {
            // Locked so two staff clicking at once cannot cancel it twice.
            $appointment = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);

            if ($appointment->status !== AppointmentStatus::Scheduled) {
                throw ValidationException::withMessages([
                    'status' => 'This appointment is no longer scheduled.',
                ]);
            }

            $appointment->forceFill([
                'status' => AppointmentStatus::Cancelled,
                'cancelled_at' => Date::now(),
                'cancelled_by' => $user->id,
                'cancelled_reason' => $reason,
            ])->save();

            $this->recordUserHistory->handle($user, UserHistoryEvent::AppointmentCancelled, [
                'appointment' => $appointment->uuid,
                'reason' => $reason,
            ]);

            return $appointment;
        });
    }
}
