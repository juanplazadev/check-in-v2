<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\AppointmentStatus;
use App\Enums\RecordHistoryEvent;
use App\Enums\UserHistoryEvent;
use App\Events\AppointmentUpdated;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Archives an appointment that has run its course (cancelled, completed,
 * missed or checked in), with an optional reason, and logs it on both the
 * appointment's timeline and the staff member's own account trail. The driver
 * is not notified.
 *
 * Archiving is a soft delete: the appointment leaves every dashboard list but
 * the archived tab, and its detail page still opens. A scheduled appointment
 * has to be cancelled first, so a booking still to come cannot drop out of the
 * live queue's arriving rail by mistake.
 *
 * A soft delete fires no updated event, so the broadcast in AppServiceProvider
 * does not run; AppointmentUpdated is dispatched here instead, after the
 * commit. The status is left alone.
 */
final readonly class ArchiveAppointmentAction
{
    public function __construct(
        private RecordHistoryAction $recordHistory,
        private RecordUserHistoryAction $recordUserHistory,
    ) {}

    /**
     * @throws ValidationException when the appointment is still scheduled.
     * @throws Throwable
     */
    public function handle(Appointment $appointment, User $user, ?string $reason = null): Appointment
    {
        return DB::transaction(function () use ($appointment, $user, $reason): Appointment {
            // Locked so two staff clicking at once cannot archive it twice. An
            // already archived appointment is out of the default scope: a 404.
            $appointment = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);

            if ($appointment->status === AppointmentStatus::Scheduled) {
                throw ValidationException::withMessages([
                    'status' => 'Scheduled appointments must be canceled before they can be archived.',
                ]);
            }

            $appointment->delete();

            $context = $reason === null ? [] : ['reason' => $reason];

            $this->recordHistory->handle($appointment, RecordHistoryEvent::Archived, user: $user, context: $context);

            $this->recordUserHistory->handle($user, UserHistoryEvent::AppointmentArchived, [
                'appointment' => $appointment->uuid,
                ...$context,
            ]);

            event(new AppointmentUpdated($appointment));

            return $appointment;
        });
    }
}
