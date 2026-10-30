<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\RecordHistoryEvent;
use App\Enums\UserHistoryEvent;
use App\Models\LoadType;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Sets who picks the load type at a location. With a default, drivers skip
 * the load type step and every booking starts as it; without one, drivers
 * choose. Bookings already made keep their load type and length.
 */
final readonly class SetDefaultLoadTypeAction
{
    private const string DRIVERS_CHOOSE = 'Drivers choose';

    public function __construct(
        private RecordHistoryAction $recordHistory,
        private RecordUserHistoryAction $recordUserHistory,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(Location $location, User $user, ?LoadType $loadType): void
    {
        DB::transaction(function () use ($location, $user, $loadType): void {
            // Locked so the default cannot change while a driver's booking is being checked against it.
            $location = Location::query()->with('defaultLoadType')->lockForUpdate()->findOrFail($location->id);

            if ($location->default_load_type_id === $loadType?->id) {
                return;
            }

            $change = [
                'from' => $this->label($location->defaultLoadType),
                'to' => $this->label($loadType),
            ];

            $location->update(['default_load_type_id' => $loadType?->id]);

            $this->recordHistory->handle($location, RecordHistoryEvent::DefaultLoadTypeChanged, user: $user, context: [
                'default_load_type' => $change,
            ]);

            $this->recordUserHistory->handle($user, UserHistoryEvent::LocationLoadTypesEdited, [
                'location' => $location->uuid,
                'default_load_type' => $change,
            ]);
        });
    }

    private function label(?LoadType $loadType): string
    {
        return $loadType instanceof LoadType ? $loadType->name : self::DRIVERS_CHOOSE;
    }
}
