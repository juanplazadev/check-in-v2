<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ScheduleFeature;
use App\Models\Location;

/**
 * Gives a new location's feature its weekly hours, as chosen in the add
 * location wizard. A day without a row is closed.
 */
final readonly class CreateLocationHoursAction
{
    /**
     * @param  array<int, array{open_time: string, close_time: string}|null>  $schedule  Keyed by Carbon's day number; null when closed. From WeeklyHours::toSchedule().
     */
    public function handle(Location $location, ScheduleFeature $feature, array $schedule): void
    {
        foreach ($schedule as $day => $hours) {
            if ($hours !== null) {
                $feature->schedule($location)->create(['day_of_week' => $day, ...$hours]);
            }
        }
    }
}
