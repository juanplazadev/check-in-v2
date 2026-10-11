<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\RecordHistoryEvent;
use App\Enums\UserHistoryEvent;
use App\Events\CheckInUpdated;
use App\Models\CheckIn;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Brings an archived check-in back to the dashboard lists, with the status it
 * was archived in, and logs it on both the check-in's timeline and the staff
 * member's own account trail. The driver is not notified.
 *
 * The restore changes no status, so the broadcast in AppServiceProvider does
 * not run; CheckInUpdated is dispatched here instead, after the commit. The
 * ERP status is left alone.
 */
final readonly class RestoreCheckInAction
{
    public function __construct(
        private RecordHistoryAction $recordHistory,
        private RecordUserHistoryAction $recordUserHistory,
    ) {}

    /**
     * @throws ValidationException when the check-in is not archived.
     * @throws Throwable
     */
    public function handle(CheckIn $checkIn, User $user): CheckIn
    {
        return DB::transaction(function () use ($checkIn, $user): CheckIn {
            // Locked so two staff clicking at once cannot restore it twice.
            $checkIn = CheckIn::query()->withTrashed()->lockForUpdate()->findOrFail($checkIn->id);

            if (! $checkIn->trashed()) {
                throw ValidationException::withMessages([
                    'status' => 'This check-in is not archived.',
                ]);
            }

            $checkIn->restore();

            $this->recordHistory->handle($checkIn, RecordHistoryEvent::Restored, user: $user);

            $this->recordUserHistory->handle($user, UserHistoryEvent::CheckInRestored, [
                'check_in' => $checkIn->uuid,
            ]);

            event(new CheckInUpdated($checkIn));

            return $checkIn;
        });
    }
}
