<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\LoadDirection;
use App\Enums\RecordHistoryEvent;
use App\Enums\UserHistoryEvent;
use App\Models\LoadType;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Adds a load type to a location. Once a location has an active load type,
 * drivers have to pick one when they book.
 */
final readonly class CreateLoadTypeAction
{
    public function __construct(
        private RecordHistoryAction $recordHistory,
        private RecordUserHistoryAction $recordUserHistory,
    ) {}

    /**
     * @param  array{name: string, direction: LoadDirection, duration_minutes: int, is_active: bool}  $attributes
     *
     * @throws Throwable
     */
    public function handle(Location $location, User $user, array $attributes): LoadType
    {
        return DB::transaction(function () use ($location, $user, $attributes): LoadType {
            $loadType = LoadType::query()->create([
                ...$attributes,
                'uuid' => Str::uuid()->toString(),
                'location_id' => $location->id,
            ]);

            $this->recordHistory->handle($location, RecordHistoryEvent::LoadTypeAdded, user: $user, context: ['loadType' => $loadType->name]);

            $this->recordUserHistory->handle($user, UserHistoryEvent::LocationLoadTypesEdited, [
                'location' => $location->uuid,
                'loadType' => $loadType->name,
            ]);

            return $loadType;
        });
    }
}
