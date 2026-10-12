<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CheckInStatus;
use App\Enums\RecordHistoryEvent;
use App\Enums\UserHistoryEvent;
use App\Models\CheckIn;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Sets a cancelled check-in back to pending, for a cancellation made by
 * mistake, and logs it on both the check-in's timeline and the staff member's
 * own account trail. The driver is not notified.
 *
 * The cancellation columns are cleared so the check-in drops out of cancelled
 * filters, sorting and exports. The reason and who cancelled it are kept in
 * the Reinstated timeline row's context, so the check-in still shows why it
 * was cancelled once it is back to pending.
 *
 * The status write also produces a StatusChanged timeline row through the
 * saved() hook in AppServiceProvider, which broadcasts the change to open
 * dashboards. The ERP status is left alone.
 */
final readonly class ReinstateCheckInAction
{
    public function __construct(
        private RecordHistoryAction $recordHistory,
        private RecordUserHistoryAction $recordUserHistory,
    ) {}

    /**
     * @throws ValidationException when the check-in is not cancelled.
     * @throws Throwable
     */
    public function handle(CheckIn $checkIn, User $user): CheckIn
    {
        return DB::transaction(function () use ($checkIn, $user): CheckIn {
            // Locked so two staff clicking at once cannot reinstate it twice.
            $checkIn = CheckIn::query()->lockForUpdate()->findOrFail($checkIn->id);

            if ($checkIn->status !== CheckInStatus::Cancelled) {
                throw ValidationException::withMessages([
                    'status' => 'This check-in is not cancelled.',
                ]);
            }

            $cancellation = [
                'cancelled_reason' => $checkIn->cancelled_reason,
                'cancelled_by' => $checkIn->cancelled_by,
            ];

            $checkIn->forceFill([
                'status' => CheckInStatus::Pending,
                'cancelled_at' => null,
                'cancelled_by' => null,
                'cancelled_reason' => null,
            ])->save();

            $this->recordHistory->handle($checkIn, RecordHistoryEvent::Reinstated, user: $user, context: $cancellation);

            $this->recordUserHistory->handle($user, UserHistoryEvent::CheckInReinstated, [
                'check_in' => $checkIn->uuid,
            ]);

            return $checkIn;
        });
    }
}
