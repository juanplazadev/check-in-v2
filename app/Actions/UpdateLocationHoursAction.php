<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ScheduleFeature;
use App\Models\AppointmentSchedule;
use App\Models\CheckInSchedule;
use App\Models\Location;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Replaces a location's weekly check-in or appointment hours and logs every
 * changed day, with its hours before and after, on both trails: the
 * location's timeline, with the editor as actor, and the editor's own account
 * trail. A save that changes nothing writes nothing, not even history.
 *
 * Rows are written and deleted one model at a time, so the model events that
 * flush the public schedule cache fire. Booked appointments are kept even when
 * the new hours leave them out; the hours page warns about them before saving.
 */
final readonly class UpdateLocationHoursAction
{
    /** Carbon's day numbers in the order the timeline lists them, Monday first. */
    private const array WEEK = [1, 2, 3, 4, 5, 6, 0];

    public function __construct(
        private RecordHistoryAction $recordHistory,
        private RecordUserHistoryAction $recordUserHistory,
    ) {}

    /**
     * @param  array<int, array{open_time: string, close_time: string}|null>  $days  Keyed by Carbon's day number; null when closed.
     * @return array<string, array{from: string, to: string}> The changed days, keyed by weekday name; empty when nothing changed.
     *
     * @throws Throwable
     */
    public function handle(Location $location, User $user, ScheduleFeature $feature, array $days): array
    {
        return DB::transaction(function () use ($location, $user, $feature, $days): array {
            // Locked so two editors saving at once cannot log from the same "before".
            $location = Location::query()->lockForUpdate()->findOrFail($location->id);

            $current = $feature->schedule($location)->get()->keyBy('day_of_week');
            $changes = [];

            foreach (self::WEEK as $day) {
                $before = $current->get($day);
                $after = $days[$day] ?? null;

                if ($this->sameHours($before, $after)) {
                    continue;
                }

                if ($after === null) {
                    $before?->delete();
                } else {
                    $feature->schedule($location)->updateOrCreate(['day_of_week' => $day], $after);
                }

                $changes[$this->weekday($day)] = [
                    'from' => $this->describe($before === null ? null : ['open_time' => $before->open_time, 'close_time' => $before->close_time]),
                    'to' => $this->describe($after),
                ];
            }

            if ($changes === []) {
                return [];
            }

            $this->recordHistory->handle($location, $feature->historyEvent(), user: $user, context: $changes);

            $this->recordUserHistory->handle($user, $feature->userHistoryEvent(), [
                'location' => $location->uuid,
                ...$changes,
            ]);

            return $changes;
        });
    }

    /**
     * @param  array{open_time: string, close_time: string}|null  $after
     */
    private function sameHours(CheckInSchedule|AppointmentSchedule|null $before, ?array $after): bool
    {
        if ($before === null || $after === null) {
            return $before === $after;
        }

        return $this->minutes($before->open_time) === $this->minutes($after['open_time'])
            && $this->minutes($before->close_time) === $this->minutes($after['close_time']);
    }

    /**
     * "07:00:00" -> "07:00". Seconds are dropped so 23:59 and 23:59:59 compare
     * as the same end of day.
     */
    private function minutes(string $time): string
    {
        return mb_substr($time, 0, 5);
    }

    /**
     * A day's hours as the timeline reads them.
     *
     * @param  array{open_time: string, close_time: string}|null  $hours
     */
    private function describe(?array $hours): string
    {
        if ($hours === null) {
            return 'Closed';
        }

        if ($this->minutes($hours['open_time']) === '00:00' && $this->minutes($hours['close_time']) === '23:59') {
            return 'Open 24 hours';
        }

        return sprintf('%s - %s', $this->clock($hours['open_time']), $this->clock($hours['close_time']));
    }

    private function clock(string $time): string
    {
        return Date::parse($time)->format('g:i A');
    }

    private function weekday(int $day): string
    {
        return mb_strtolower(Date::now()->startOfWeek(CarbonInterface::SUNDAY)->addDays($day)->englishDayOfWeek);
    }
}
