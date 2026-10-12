<?php

declare(strict_types=1);

namespace App\Actions;

use App\Dashboard\CheckInDeletionImpact;
use App\Enums\RecordHistoryEvent;
use App\Enums\UserHistoryEvent;
use App\Events\CheckInDeleted;
use App\Models\Appointment;
use App\Models\CheckIn;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Permanently deletes a check-in in any status, archived included, with every
 * row that hangs off it: its timeline, notes, purchase orders, short link, the
 * staff bell notifications announcing it and any pending account claim. The
 * driver is not notified.
 *
 * The check-in's own timeline goes with it, so the deletion is logged on the
 * staff member's account trail, with a snapshot and the reason, and on the
 * timeline of the appointment it was converted from, if any. That appointment
 * is otherwise left as it is.
 *
 * CheckInDeleted carries the uuid and location rather than the model, which no
 * longer exists by the time the broadcast is queued.
 */
final readonly class DeleteCheckInAction
{
    public function __construct(
        private CheckInDeletionImpact $impact,
        private RecordHistoryAction $recordHistory,
        private RecordUserHistoryAction $recordUserHistory,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(CheckIn $checkIn, User $user, string $reason): void
    {
        DB::transaction(function () use ($checkIn, $user, $reason): void {
            // Locked so two admins clicking at once cannot delete it twice;
            // the second one finds nothing: a 404.
            $checkIn = CheckIn::query()->withTrashed()->lockForUpdate()->findOrFail($checkIn->id);

            $appointment = $checkIn->appointment;

            $snapshot = [
                'check_in' => $checkIn->uuid,
                'reference_number' => $checkIn->reference_number,
                'location' => $checkIn->location->name,
                'status' => $checkIn->status->value,
                'archived' => $checkIn->trashed(),
                'appointment' => $appointment?->uuid,
                'reason' => $reason,
            ];

            $checkIn->history()->reorder()->delete();
            $checkIn->notes()->reorder()->delete();
            $checkIn->purchaseOrders()->delete();
            $checkIn->shortLink()->delete();
            $this->impact->staffNotifications($checkIn)->delete();
            $this->impact->pendingClaims($checkIn)->update(['pending_check_in_id' => null]);

            $checkIn->forceDelete();

            if ($appointment instanceof Appointment) {
                $this->recordHistory->handle($appointment, RecordHistoryEvent::CheckInDeleted, user: $user, context: [
                    'reference_number' => $checkIn->reference_number,
                    'reason' => $reason,
                ]);
            }

            $this->recordUserHistory->handle($user, UserHistoryEvent::CheckInDeleted, $snapshot);

            event(new CheckInDeleted($checkIn->uuid, $checkIn->location_id));
        });
    }
}
