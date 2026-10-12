<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CheckInStatus;
use App\Enums\UserHistoryEvent;
use App\Models\CheckIn;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Cancels a pending check-in on behalf of a staff member, keeping who did it
 * and why, and logs it on the staff member's own account trail. The driver is
 * not notified.
 *
 * The status write is what produces the check-in's timeline row: the saved()
 * hook in AppServiceProvider records a StatusChanged row, credited to the
 * signed-in user, and broadcasts the change to open dashboards. Nothing is
 * written to the check-in trail by hand here. The ERP status is left alone.
 */
final readonly class CancelCheckInAction
{
    public function __construct(private RecordUserHistoryAction $recordUserHistory) {}

    /**
     * @throws ValidationException when the check-in is no longer pending.
     * @throws Throwable
     */
    public function handle(CheckIn $checkIn, User $user, string $reason): CheckIn
    {
        return DB::transaction(function () use ($checkIn, $user, $reason): CheckIn {
            // Locked so two staff clicking at once cannot cancel it twice.
            $checkIn = CheckIn::query()->lockForUpdate()->findOrFail($checkIn->id);

            if ($checkIn->status !== CheckInStatus::Pending) {
                throw ValidationException::withMessages([
                    'status' => 'This check-in is no longer pending.',
                ]);
            }

            $checkIn->forceFill([
                'status' => CheckInStatus::Cancelled,
                'cancelled_at' => Date::now(),
                'cancelled_by' => $user->id,
                'cancelled_reason' => $reason,
            ])->save();

            $this->recordUserHistory->handle($user, UserHistoryEvent::CheckInCancelled, [
                'check_in' => $checkIn->uuid,
                'reason' => $reason,
            ]);

            return $checkIn;
        });
    }
}
