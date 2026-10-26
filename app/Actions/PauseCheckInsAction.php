<?php

declare(strict_types=1);

namespace App\Actions;

use App\CheckIn\CheckInScheduleResolver;
use App\Enums\CheckInPauseDuration;
use App\Enums\RecordHistoryEvent;
use App\Enums\UserHistoryEvent;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pauses new check-ins at a location, for a while or until resumed, and logs
 * it on both the location's timeline and the acting staff member's trail.
 * Appointments are unaffected. Pausing an already paused location replaces
 * the pause, so staff can extend it or change the reason.
 */
final readonly class PauseCheckInsAction
{
    public function __construct(
        private CheckInScheduleResolver $schedule,
        private RecordHistoryAction $recordHistory,
        private RecordUserHistoryAction $recordUserHistory,
    ) {}

    /**
     * @return Location The paused location.
     *
     * @throws Throwable
     */
    public function handle(Location $location, User $actor, string $reason, CheckInPauseDuration $duration): Location
    {
        return DB::transaction(function () use ($location, $actor, $reason, $duration): Location {
            // Locked so two staff pausing at once cannot interleave.
            $location = Location::query()->lockForUpdate()->findOrFail($location->id);

            $now = now()->setTimezone($location->timezone);
            $until = $duration->endsAt($now, $this->schedule->resolveSchedule($location, $now)->closeTime);

            $location->update([
                // Stored in app time; Eloquent writes a Carbon's wall clock as is.
                'checkins_paused_at' => $now->utc(),
                'checkins_paused_until' => $until?->utc(),
                'checkins_paused_reason' => $reason,
            ]);

            $context = [
                'reason' => $reason,
                'duration' => $duration->value,
                // Local to the location, so the timeline reads in its time.
                'until' => $until?->toIso8601String(),
            ];

            $this->recordHistory->handle($location, RecordHistoryEvent::CheckInsPaused, user: $actor, context: $context);

            $this->recordUserHistory->handle($actor, UserHistoryEvent::LocationCheckInsPaused, [
                'location' => $location->uuid,
                ...$context,
            ]);

            return $location;
        });
    }
}
