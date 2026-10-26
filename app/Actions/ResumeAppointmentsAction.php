<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\RecordHistoryEvent;
use App\Enums\UserHistoryEvent;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Ends a booking pause early and logs it on both the location's timeline and
 * the acting staff member's trail. A pause that already ended on its own is
 * cleared without a log, since nothing changes for guests.
 */
final readonly class ResumeAppointmentsAction
{
    public function __construct(
        private RecordHistoryAction $recordHistory,
        private RecordUserHistoryAction $recordUserHistory,
    ) {}

    /**
     * @return bool Whether appointments were paused.
     *
     * @throws Throwable
     */
    public function handle(Location $location, User $actor): bool
    {
        return DB::transaction(function () use ($location, $actor): bool {
            $location = Location::query()->lockForUpdate()->findOrFail($location->id);

            if ($location->appointments_paused_at === null) {
                return false;
            }

            $wasPaused = $location->isAppointmentPaused(now());
            $reason = $location->appointments_paused_reason;

            $location->update([
                'appointments_paused_at' => null,
                'appointments_paused_until' => null,
                'appointments_paused_reason' => null,
            ]);

            if (! $wasPaused) {
                return false;
            }

            $this->recordHistory->handle($location, RecordHistoryEvent::AppointmentsResumed, user: $actor, context: [
                'reason' => $reason,
            ]);

            $this->recordUserHistory->handle($actor, UserHistoryEvent::LocationAppointmentsResumed, [
                'location' => $location->uuid,
            ]);

            return true;
        });
    }
}
