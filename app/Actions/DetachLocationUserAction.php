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
 * Detaches a staff member from a location, so a location-scoped user stops
 * seeing its check-ins and appointments, and logs it on both the location's
 * timeline and the acting staff member's trail.
 *
 * users.location_id is a driver's booking default, not a staff tie, so it is
 * left alone.
 */
final readonly class DetachLocationUserAction
{
    public function __construct(
        private RecordHistoryAction $recordHistory,
        private RecordUserHistoryAction $recordUserHistory,
    ) {}

    /**
     * @return bool Whether the user was attached.
     *
     * @throws Throwable
     */
    public function handle(Location $location, User $user, User $actor): bool
    {
        return DB::transaction(function () use ($location, $user, $actor): bool {
            if ($location->users()->detach($user->id) === 0) {
                return false;
            }

            $this->recordHistory->handle($location, RecordHistoryEvent::LocationUserDetached, user: $actor, context: [
                'user' => $user->uuid,
                'name' => $user->name,
            ]);

            $this->recordUserHistory->handle($actor, UserHistoryEvent::LocationUserDetached, [
                'location' => $location->uuid,
                'user' => $user->uuid,
            ]);

            return true;
        });
    }
}
