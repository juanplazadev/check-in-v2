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
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Renames a load type, changes its direction or duration, or turns it off,
 * and logs what changed. A save that changes nothing writes no history.
 *
 * Bookings already made keep the end time they were booked with, so a new
 * duration only applies to bookings made from now on.
 *
 * The location's default load type cannot be turned off; staff pick another
 * default, or let drivers choose, first.
 */
final readonly class UpdateLoadTypeAction
{
    public function __construct(
        private RecordHistoryAction $recordHistory,
        private RecordUserHistoryAction $recordUserHistory,
    ) {}

    /**
     * @param  array{name: string, direction: LoadDirection, duration_minutes: int, is_active: bool}  $attributes
     *
     * @throws ValidationException When the location's default load type is being turned off.
     * @throws Throwable
     */
    public function handle(LoadType $loadType, User $user, array $attributes): void
    {
        DB::transaction(function () use ($loadType, $user, $attributes): void {
            // Locked so the default cannot be set on this load type while it is being turned off.
            $location = Location::query()->lockForUpdate()->findOrFail($loadType->location_id);

            if (! $attributes['is_active'] && $location->default_load_type_id === $loadType->id) {
                throw ValidationException::withMessages([
                    'is_active' => 'This is the default load type. Choose another default first.',
                ]);
            }

            $before = $this->snapshot($loadType);
            $loadType->update($attributes);
            $after = $this->snapshot($loadType->refresh());

            $changes = [];

            foreach ($after as $field => $value) {
                if ($before[$field] !== $value) {
                    $changes[$field] = ['from' => $before[$field], 'to' => $value];
                }
            }

            if ($changes === []) {
                return;
            }

            $this->recordHistory->handle($loadType->location, RecordHistoryEvent::LoadTypeEdited, user: $user, context: [
                'loadType' => $loadType->name,
                ...$changes,
            ]);

            $this->recordUserHistory->handle($user, UserHistoryEvent::LocationLoadTypesEdited, [
                'location' => $loadType->location->uuid,
                'loadType' => $loadType->name,
                ...$changes,
            ]);
        });
    }

    /**
     * @return array{name: string, direction: string, duration_minutes: int, is_active: bool}
     */
    private function snapshot(LoadType $loadType): array
    {
        return [
            'name' => $loadType->name,
            'direction' => $loadType->direction->value,
            'duration_minutes' => $loadType->duration_minutes,
            'is_active' => $loadType->is_active,
        ];
    }
}
