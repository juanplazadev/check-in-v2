<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\RecordHistoryEvent;
use App\Enums\ScheduleFeature;
use App\Enums\UserHistoryEvent;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Closes a location for a day, or sets special hours, for each feature given,
 * and logs it once on both trails: the location's timeline, with the editor
 * as actor, and the editor's own account trail.
 *
 * Rows are created one model at a time, so the model events that flush the
 * public schedule cache fire. Booked appointments on the date are kept; the
 * closures page warns about them before saving.
 */
final readonly class AddLocationClosureAction
{
    public function __construct(
        private RecordHistoryAction $recordHistory,
        private RecordUserHistoryAction $recordUserHistory,
    ) {}

    /**
     * @param  list<ScheduleFeature>  $features
     * @param  array{override_date: string, is_closed: bool, open_time: string|null, close_time: string|null, reason: string}  $closure
     *
     * @throws ValidationException When a feature already has a closure on the date.
     * @throws Throwable
     */
    public function handle(Location $location, User $user, array $features, array $closure): void
    {
        DB::transaction(function () use ($location, $user, $features, $closure): void {
            // Locked so two editors adding the same date at once cannot both pass the check below.
            $location = Location::query()->lockForUpdate()->findOrFail($location->id);

            foreach ($features as $feature) {
                // A feature holds one closure per date, so an existing one has to be removed first.
                if ($feature->overrides($location)->whereDate('override_date', $closure['override_date'])->exists()) {
                    throw ValidationException::withMessages([
                        'applies_to' => sprintf('%ss already have a closure on this date. Remove it first.', $feature->label()),
                    ]);
                }

                $feature->overrides($location)->create($closure);
            }

            $context = [
                'date' => $closure['override_date'],
                'reason' => $closure['reason'],
                'hours' => $this->describe($closure),
                'appliesTo' => array_map(fn (ScheduleFeature $feature): string => $feature->closureKey(), $features),
            ];

            $this->recordHistory->handle($location, RecordHistoryEvent::ClosureAdded, user: $user, context: $context);

            $this->recordUserHistory->handle($user, UserHistoryEvent::LocationClosureAdded, [
                'location' => $location->uuid,
                ...$context,
            ]);
        });
    }

    /**
     * The day's hours as the timeline reads them.
     *
     * @param  array{is_closed: bool, open_time: string|null, close_time: string|null}  $closure
     */
    private function describe(array $closure): string
    {
        if ($closure['is_closed']) {
            return 'Closed';
        }

        return sprintf(
            '%s - %s',
            Date::parse((string) $closure['open_time'])->format('g:i A'),
            Date::parse((string) $closure['close_time'])->format('g:i A'),
        );
    }
}
