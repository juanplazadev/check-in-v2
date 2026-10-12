<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\AppointmentLinkMethod;
use App\Enums\AppointmentStatus;
use App\Enums\RecordHistoryEvent;
use App\Enums\UserHistoryEvent;
use App\Events\CheckInUpdated;
use App\Models\Appointment;
use App\Models\CheckIn;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Undoes a staff link made by mistake: the check-in drops its booking, the
 * booking goes back to scheduled, and both timelines and the staff member's
 * account trail record it.
 *
 * Only staff links can be undone. A conversion is the driver turning the
 * booking into this check-in, so there is no separate record to fall back to.
 */
final readonly class UnlinkCheckInAppointmentAction
{
    public function __construct(
        private RecordHistoryAction $recordHistory,
        private RecordUserHistoryAction $recordUserHistory,
    ) {}

    /**
     * @throws ValidationException when the check-in has no staff link.
     * @throws Throwable
     */
    public function handle(CheckIn $checkIn, User $user): CheckIn
    {
        return DB::transaction(function () use ($checkIn, $user): CheckIn {
            $checkIn = CheckIn::query()->lockForUpdate()->findOrFail($checkIn->id);

            if ($checkIn->appointment_id === null) {
                throw ValidationException::withMessages([
                    'appointment' => 'This check-in is not linked to an appointment.',
                ]);
            }

            if ($checkIn->appointment_linked_via !== AppointmentLinkMethod::Staff) {
                throw ValidationException::withMessages([
                    'appointment' => 'Only appointments linked by staff can be unlinked.',
                ]);
            }

            $appointment = Appointment::query()->withTrashed()->lockForUpdate()->find($checkIn->appointment_id);

            $checkIn->forceFill([
                'appointment_id' => null,
                'appointment_linked_at' => null,
                'appointment_linked_via' => null,
                'appointment_linked_by' => null,
            ])->save();

            if ($appointment instanceof Appointment) {
                $appointment->forceFill(['status' => AppointmentStatus::Scheduled])->save();

                $this->recordHistory->handle($appointment, RecordHistoryEvent::AppointmentUnlinked, user: $user, context: [
                    'reference_number' => $checkIn->reference_number,
                ]);
            }

            $this->recordHistory->handle($checkIn, RecordHistoryEvent::AppointmentUnlinked, user: $user, context: [
                'reference_number' => $appointment?->reference_number,
            ]);

            $this->recordUserHistory->handle($user, UserHistoryEvent::CheckInAppointmentUnlinked, [
                'check_in' => $checkIn->uuid,
                'appointment' => $appointment?->uuid,
            ]);

            event(new CheckInUpdated($checkIn));

            return $checkIn;
        });
    }
}
