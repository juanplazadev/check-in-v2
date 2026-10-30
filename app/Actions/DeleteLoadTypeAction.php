<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\RecordHistoryEvent;
use App\Enums\UserHistoryEvent;
use App\Models\LoadType;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Removes a load type that was added by mistake. One that was ever booked,
 * archived bookings included, is kept so those bookings still name their
 * load; staff turn it off instead.
 */
final readonly class DeleteLoadTypeAction
{
    public function __construct(
        private RecordHistoryAction $recordHistory,
        private RecordUserHistoryAction $recordUserHistory,
    ) {}

    /**
     * @throws ValidationException When the load type has ever been booked.
     * @throws Throwable
     */
    public function handle(LoadType $loadType, User $user): void
    {
        DB::transaction(function () use ($loadType, $user): void {
            // Locked so a driver cannot book the load type while it is being removed.
            $location = Location::query()->lockForUpdate()->findOrFail($loadType->location_id);

            if ($loadType->appointments()->exists()) {
                throw ValidationException::withMessages([
                    'loadType' => 'Appointments have been booked with this load type, so it cannot be removed. Turn it off instead.',
                ]);
            }

            $name = $loadType->name;
            $loadType->delete();

            $this->recordHistory->handle($location, RecordHistoryEvent::LoadTypeRemoved, user: $user, context: ['loadType' => $name]);

            $this->recordUserHistory->handle($user, UserHistoryEvent::LocationLoadTypesEdited, [
                'location' => $location->uuid,
                'loadType' => $name,
            ]);
        });
    }
}
