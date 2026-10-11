<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CheckInStatus;
use App\Enums\UserHistoryEvent;
use App\Models\CheckIn;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Sets a completed check-in back to pending, for a completion made by mistake,
 * and logs it on the staff member's own account trail.
 *
 * The completion columns are cleared so the check-in drops out of completed
 * date filters, sorting and exports. Who completed it first stays on record in
 * the earlier timeline row and on that person's own trail.
 *
 * The status write is what produces the check-in's timeline row: the saved()
 * hook in AppServiceProvider records a StatusChanged row, credited to the
 * signed-in user, and broadcasts the change to open dashboards. Nothing is
 * written to the check-in trail by hand here. The ERP status is left alone.
 */
final readonly class ReopenCheckInAction
{
    public function __construct(private RecordUserHistoryAction $recordUserHistory) {}

    /**
     * @throws ValidationException when the check-in is not completed.
     * @throws Throwable
     */
    public function handle(CheckIn $checkIn, User $user): CheckIn
    {
        return DB::transaction(function () use ($checkIn, $user): CheckIn {
            // Locked so two staff clicking at once cannot reopen it twice.
            $checkIn = CheckIn::query()->lockForUpdate()->findOrFail($checkIn->id);

            if ($checkIn->status !== CheckInStatus::Completed) {
                throw ValidationException::withMessages([
                    'status' => 'This check-in is not completed.',
                ]);
            }

            $checkIn->forceFill([
                'status' => CheckInStatus::Pending,
                'completed_at' => null,
                'completed_by' => null,
            ])->save();

            $this->recordUserHistory->handle($user, UserHistoryEvent::CheckInReopened, [
                'check_in' => $checkIn->uuid,
            ]);

            return $checkIn;
        });
    }
}
