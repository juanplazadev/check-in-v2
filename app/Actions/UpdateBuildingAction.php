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
 * Renames a building. A save that changes nothing writes no history.
 */
final readonly class UpdateBuildingAction
{
    public function __construct(
        private RecordHistoryAction $recordHistory,
        private RecordUserHistoryAction $recordUserHistory,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(Building $building, User $user, string $name): void
    {
        $from = $building->name;

        if ($from === $name) {
            return;
        }

        DB::transaction(function () use ($building, $user, $name, $from): void {
            $building->update(['name' => $name]);

            $this->recordHistory->handle($building->location, RecordHistoryEvent::BuildingEdited, user: $user, context: [
                'from' => $from,
                'to' => $name,
            ]);

            $this->recordUserHistory->handle($user, UserHistoryEvent::LocationDocksEdited, [
                'location' => $building->location->uuid,
                'building' => $name,
            ]);
        });
    }
}
