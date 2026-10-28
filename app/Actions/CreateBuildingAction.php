<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\RecordHistoryEvent;
use App\Enums\UserHistoryEvent;
use App\Models\Building;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Adds a building to a location's campus, for grouping its docks, and logs
 * it on the location's timeline and the editor's own trail.
 */
final readonly class CreateBuildingAction
{
    public function __construct(
        private RecordHistoryAction $recordHistory,
        private RecordUserHistoryAction $recordUserHistory,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(Location $location, User $user, string $name): Building
    {
        return DB::transaction(function () use ($location, $user, $name): Building {
            $building = Building::query()->create([
                'uuid' => Str::uuid()->toString(),
                'location_id' => $location->id,
                'name' => $name,
                'sort_order' => Building::query()->whereBelongsTo($location)->count(),
            ]);

            $this->recordHistory->handle($location, RecordHistoryEvent::BuildingAdded, user: $user, context: ['name' => $name]);

            $this->recordUserHistory->handle($user, UserHistoryEvent::LocationDocksEdited, [
                'location' => $location->uuid,
                'building' => $name,
            ]);

            return $building;
        });
    }
}
