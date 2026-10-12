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
 * Marks a pending check-in completed by a staff member and logs it on the
 * staff member's own account trail.
 *
 * The status write is what produces the check-in's timeline row: the saved()
 * hook in AppServiceProvider records a StatusChanged row, credited to the
 * signed-in user, and broadcasts the change to open dashboards. Nothing is
 * written to the check-in trail by hand here.
 */
final readonly class MarkCheckInCompletedAction
{
    public function __construct(private RecordUserHistoryAction $recordUserHistory) {}

    /**
     * @throws ValidationException when the check-in is no longer pending.
     * @throws Throwable
     */
    public function handle(CheckIn $checkIn, User $user): CheckIn
    {
        return DB::transaction(function () use ($checkIn, $user): CheckIn {
            // Locked so two staff clicking at once cannot complete it twice.
            $checkIn = CheckIn::query()->lockForUpdate()->findOrFail($checkIn->id);

            if ($checkIn->status !== CheckInStatus::Pending) {
                throw ValidationException::withMessages([
                    'status' => 'This check-in is no longer pending.',
                ]);
            }

            $checkIn->forceFill([
                'status' => CheckInStatus::Completed,
                'completed_at' => Date::now(),
                'completed_by' => $user->id,
            ])->save();

            $this->recordUserHistory->handle($user, UserHistoryEvent::CheckInCompleted, [
                'check_in' => $checkIn->uuid,
            ]);

            return $checkIn;
        });
    }
}
