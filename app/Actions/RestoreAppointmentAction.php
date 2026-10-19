<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\RecordHistoryEvent;
use App\Enums\UserHistoryEvent;
use App\Events\AppointmentUpdated;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Brings an archived appointment back to the dashboard lists, with the status
 * it was archived in, and logs it on both the appointment's timeline and the
 * staff member's own account trail. The driver is not notified.
 *
 * The restore changes neither the status nor the time slot, so the broadcast
 * in AppServiceProvider does not run; AppointmentUpdated is dispatched here
 * instead, after the commit.
 */
final readonly class RestoreAppointmentAction
{
    public function __construct(
        private RecordHistoryAction $recordHistory,
        private RecordUserHistoryAction $recordUserHistory,
    ) {}

    /**
     * @throws ValidationException when the appointment is not archived.
     * @throws Throwable
     */
    public function handle(Appointment $appointment, User $user): Appointment
    {
        return DB::transaction(function () use ($appointment, $user): Appointment {
            // Locked so two staff clicking at once cannot restore it twice.
            $appointment = Appointment::query()->withTrashed()->lockForUpdate()->findOrFail($appointment->id);

            if (! $appointment->trashed()) {
                throw ValidationException::withMessages([
                    'status' => 'This appointment is not archived.',
                ]);
            }

            $appointment->restore();

            $this->recordHistory->handle($appointment, RecordHistoryEvent::Restored, user: $user);

            $this->recordUserHistory->handle($user, UserHistoryEvent::AppointmentRestored, [
                'appointment' => $appointment->uuid,
            ]);

            event(new AppointmentUpdated($appointment));

            return $appointment;
        });
    }
}
