<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\AppointmentPauseDuration;
use App\Enums\RecordHistoryEvent;
use App\Enums\UserHistoryEvent;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pauses new bookings at a location, for a while or until resumed, and logs
 * it on both the location's timeline and the acting staff member's trail.
 * Existing appointments and check-ins are unaffected. Pausing an already
 * paused location replaces the pause, so staff can extend it or change the
 * reason.
 */
final readonly class PauseAppointmentsAction
{
    public function __construct(
        private RecordHistoryAction $recordHistory,
        private RecordUserHistoryAction $recordUserHistory,
    ) {}

    /**
     * @return Location The paused location.
     *
     * @throws Throwable
     */
    public function handle(Location $location, User $actor, string $reason, AppointmentPauseDuration $duration): Location
    {
        return DB::transaction(function () use ($location, $actor, $reason, $duration): Location {
            // Locked so two staff pausing at once cannot interleave.
            $location = Location::query()->lockForUpdate()->findOrFail($location->id);

            $now = now()->setTimezone($location->timezone);
            $until = $duration->endsAt($now);

            $location->update([
                // Stored in app time; Eloquent writes a Carbon's wall clock as is.
                'appointments_paused_at' => $now->utc(),
                'appointments_paused_until' => $until?->utc(),
                'appointments_paused_reason' => $reason,
            ]);

            $context = [
                'reason' => $reason,
                'duration' => $duration->value,
                // Local to the location, so the timeline reads in its time.
                'until' => $until?->toIso8601String(),
            ];

            $this->recordHistory->handle($location, RecordHistoryEvent::AppointmentsPaused, user: $actor, context: $context);

            $this->recordUserHistory->handle($actor, UserHistoryEvent::LocationAppointmentsPaused, [
                'location' => $location->uuid,
                ...$context,
            ]);

            return $location;
        });
    }
}
