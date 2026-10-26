<?php

declare(strict_types=1);

namespace App\Actions;

use App\Dashboard\AppointmentDeletionImpact;
use App\Enums\RecordHistoryEvent;
use App\Enums\UserHistoryEvent;
use App\Events\AppointmentDeleted;
use App\Events\CheckInUpdated;
use App\Models\Appointment;
use App\Models\CheckIn;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Permanently deletes an appointment in any status, archived included, with
 * every row that hangs off it: its timeline, notes, purchase orders, short
 * link, the staff bell notifications announcing it and any pending account
 * claim. The driver is not notified.
 *
 * The appointment's own timeline goes with it, so the deletion is logged on
 * the staff member's account trail, with a snapshot and the reason, and on the
 * timeline of the check-in it was converted to or linked with, if any. That
 * check-in is kept, unlinked from the booking.
 *
 * AppointmentDeleted carries the uuid and location rather than the model,
 * which no longer exists by the time the broadcast is queued.
 */
final readonly class DeleteAppointmentAction
{
    public function __construct(
        private AppointmentDeletionImpact $impact,
        private RecordHistoryAction $recordHistory,
        private RecordUserHistoryAction $recordUserHistory,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(Appointment $appointment, User $user, string $reason): void
    {
        DB::transaction(function () use ($appointment, $user, $reason): void {
            // Locked so two admins clicking at once cannot delete it twice;
            // the second one finds nothing: a 404.
            $appointment = Appointment::query()->withTrashed()->lockForUpdate()->findOrFail($appointment->id);

            $checkIn = CheckIn::query()->withTrashed()->lockForUpdate()->where('appointment_id', $appointment->id)->first();

            $snapshot = [
                'appointment' => $appointment->uuid,
                'reference_number' => $appointment->reference_number,
                'location' => $appointment->location->name,
                'status' => $appointment->status->value,
                'scheduled_for' => $appointment->scheduled_for->toIso8601String(),
                'archived' => $appointment->trashed(),
                'check_in' => $checkIn?->uuid,
                'reason' => $reason,
            ];

            $appointment->history()->reorder()->delete();
            $appointment->notes()->reorder()->delete();
            $appointment->purchaseOrders()->delete();
            $appointment->shortLink()->delete();
            $this->impact->staffNotifications($appointment)->delete();
            $this->impact->pendingClaims($appointment)->update(['pending_appointment_id' => null]);

            if ($checkIn instanceof CheckIn) {
                $checkIn->forceFill([
                    'appointment_id' => null,
                    'appointment_linked_at' => null,
                    'appointment_linked_via' => null,
                    'appointment_linked_by' => null,
                ])->save();
            }

            $appointment->forceDelete();

            if ($checkIn instanceof CheckIn) {
                $this->recordHistory->handle($checkIn, RecordHistoryEvent::AppointmentDeleted, user: $user, context: [
                    'reference_number' => $appointment->reference_number,
                    'reason' => $reason,
                ]);

                event(new CheckInUpdated($checkIn));
            }

            $this->recordUserHistory->handle($user, UserHistoryEvent::AppointmentDeleted, $snapshot);

            event(new AppointmentDeleted($appointment->uuid, $appointment->location_id));
        });
    }
}
