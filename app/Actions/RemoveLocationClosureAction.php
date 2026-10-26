<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\RecordHistoryEvent;
use App\Enums\ScheduleFeature;
use App\Enums\UserHistoryEvent;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Removes a location's closure or special hours on a date from each feature
 * given, so that date follows the weekly hours again, and logs it once on
 * both trails. A feature without a closure on the date is skipped, and when
 * none had one nothing is written, not even history.
 *
 * Rows are deleted one model at a time, so the model events that flush the
 * public schedule cache fire.
 */
final readonly class RemoveLocationClosureAction
{
    public function __construct(
        private RecordHistoryAction $recordHistory,
        private RecordUserHistoryAction $recordUserHistory,
    ) {}

    /**
     * @param  list<ScheduleFeature>  $features
     * @return list<ScheduleFeature> The features a closure was removed from; empty when none had one.
     *
     * @throws Throwable
     */
    public function handle(Location $location, User $user, string $date, array $features): array
    {
        return DB::transaction(function () use ($location, $user, $date, $features): array {
            // Locked so two editors removing at once cannot both log the same removal.
            $location = Location::query()->lockForUpdate()->findOrFail($location->id);

            $removed = [];
            $reason = null;

            foreach ($features as $feature) {
                $override = $feature->overrides($location)->whereDate('override_date', $date)->first();

                if ($override === null) {
                    continue;
                }

                $reason ??= $override->reason;
                $override->delete();
                $removed[] = $feature;
            }

            if ($removed === []) {
                return [];
            }

            $context = [
                'date' => $date,
                'reason' => $reason,
                'appliesTo' => array_map(fn (ScheduleFeature $feature): string => $feature->closureKey(), $removed),
            ];

            $this->recordHistory->handle($location, RecordHistoryEvent::ClosureRemoved, user: $user, context: $context);

            $this->recordUserHistory->handle($user, UserHistoryEvent::LocationClosureRemoved, [
                'location' => $location->uuid,
                ...$context,
            ]);

            return $removed;
        });
    }
}
