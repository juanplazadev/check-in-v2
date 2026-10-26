<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\RecordHistoryEvent;
use App\Enums\UserHistoryEvent;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Brings a soft-deleted location back, with its staff, schedules and status
 * flags as they were, and logs it on both the location's timeline and the
 * acting staff member's trail.
 */
final readonly class RestoreLocationAction
{
    public function __construct(
        private RecordHistoryAction $recordHistory,
        private RecordUserHistoryAction $recordUserHistory,
    ) {}

    /**
     * @throws ValidationException when the location is not deleted.
     * @throws Throwable
     */
    public function handle(Location $location, User $user): Location
    {
        return DB::transaction(function () use ($location, $user): Location {
            // Locked so two admins clicking at once cannot restore it twice.
            $location = Location::query()->withTrashed()->lockForUpdate()->findOrFail($location->id);

            if (! $location->trashed()) {
                throw ValidationException::withMessages([
                    'location' => 'This location is not deleted.',
                ]);
            }

            $location->restore();

            $this->recordHistory->handle($location, RecordHistoryEvent::LocationRestored, user: $user);

            $this->recordUserHistory->handle($user, UserHistoryEvent::LocationRestored, [
                'location' => $location->uuid,
            ]);

            return $location;
        });
    }
}
