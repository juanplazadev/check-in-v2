<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CheckInStatus;
use App\Enums\RecordHistoryEvent;
use App\Enums\UserHistoryEvent;
use App\Events\CheckInUpdated;
use App\Models\CheckIn;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Archives a completed or cancelled check-in, with an optional reason, and logs
 * it on both the check-in's timeline and the staff member's own account trail.
 * The driver is not notified.
 *
 * Archiving is a soft delete: the check-in leaves every dashboard list but the
 * archived tab, and its detail page still opens. A pending check-in has to be
 * completed or cancelled first, so a truck still on site cannot drop out of
 * the live queue by mistake.
 *
 * A soft delete fires no updated event, so the broadcast in AppServiceProvider
 * does not run; CheckInUpdated is dispatched here instead, after the commit.
 * The status and ERP status are left alone.
 */
final readonly class ArchiveCheckInAction
{
    public function __construct(
        private RecordHistoryAction $recordHistory,
        private RecordUserHistoryAction $recordUserHistory,
    ) {}

    /**
     * @throws ValidationException when the check-in is still pending.
     * @throws Throwable
     */
    public function handle(CheckIn $checkIn, User $user, ?string $reason = null): CheckIn
    {
        return DB::transaction(function () use ($checkIn, $user, $reason): CheckIn {
            // Locked so two staff clicking at once cannot archive it twice. An
            // already archived check-in is out of the default scope: a 404.
            $checkIn = CheckIn::query()->lockForUpdate()->findOrFail($checkIn->id);

            if (! in_array($checkIn->status, [CheckInStatus::Completed, CheckInStatus::Cancelled], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Only completed or canceled check-ins can be archived.',
                ]);
            }

            $checkIn->delete();

            $context = $reason === null ? [] : ['reason' => $reason];

            $this->recordHistory->handle($checkIn, RecordHistoryEvent::Archived, user: $user, context: $context);

            $this->recordUserHistory->handle($user, UserHistoryEvent::CheckInArchived, [
                'check_in' => $checkIn->uuid,
                ...$context,
            ]);

            event(new CheckInUpdated($checkIn));

            return $checkIn;
        });
    }
}
