<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\RecordHistoryEvent;
use App\Enums\UserHistoryEvent;
use App\Models\Building;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Removes a building. Its docks stay, ungrouped, so no appointment loses
 * its dock; the foreign key clears their building.
 */
final readonly class DeleteBuildingAction
{
    public function __construct(
        private RecordHistoryAction $recordHistory,
        private RecordUserHistoryAction $recordUserHistory,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(Building $building, User $user): void
    {
        DB::transaction(function () use ($building, $user): void {
            $location = $building->location;

            $building->delete();

            $this->recordHistory->handle($location, RecordHistoryEvent::BuildingRemoved, user: $user, context: ['name' => $building->name]);

            $this->recordUserHistory->handle($user, UserHistoryEvent::LocationDocksEdited, [
                'location' => $location->uuid,
                'building' => $building->name,
            ]);
        });
    }
}
