<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\RecordHistoryEvent;
use App\Enums\UserHistoryEvent;
use App\Models\Dock;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Adds a dock to a location. An active dock that accepts appointments adds
 * one booking to every appointment slot from now on.
 */
final readonly class CreateDockAction
{
    public function __construct(
        private RecordHistoryAction $recordHistory,
        private RecordUserHistoryAction $recordUserHistory,
    ) {}

    /**
     * @param  array{name: string, building_id: int|null, is_active: bool, accepts_appointments: bool}  $attributes
     *
     * @throws Throwable
     */
    public function handle(Location $location, User $user, array $attributes): Dock
    {
        return DB::transaction(function () use ($location, $user, $attributes): Dock {
            $dock = Dock::query()->create([
                ...$attributes,
                'uuid' => Str::uuid()->toString(),
                'location_id' => $location->id,
            ]);

            $this->recordHistory->handle($location, RecordHistoryEvent::DockAdded, user: $user, context: ['dock' => $dock->label()]);

            $this->recordUserHistory->handle($user, UserHistoryEvent::LocationDocksEdited, [
                'location' => $location->uuid,
                'dock' => $dock->label(),
            ]);

            return $dock;
        });
    }
}
