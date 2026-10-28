<?php

declare(strict_types=1);

namespace App\Appointment;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Dock;
use App\Models\Location;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;

/**
 * How many bookings an appointment slot holds at a location: one per active
 * dock that accepts appointments. A location with no such docks has no limit,
 * as before docks existed.
 *
 * Until load types give appointments a duration, a booking fills exactly the
 * slot it starts in.
 */
final readonly class AppointmentSlotCapacity
{
    /**
     * Statuses that still hold their slot. A canceled or no-show booking
     * frees it for someone else.
     *
     * @var list<AppointmentStatus>
     */
    public const array OCCUPYING_STATUSES = [
        AppointmentStatus::Scheduled,
        AppointmentStatus::CheckedIn,
        AppointmentStatus::Completed,
    ];

    /**
     * Bookings per slot, or null when the location has no limit.
     */
    public function capacityFor(Location $location): ?int
    {
        $docks = Dock::query()->whereBelongsTo($location)->bookable()->count();

        return $docks === 0 ? null : $docks;
    }

    public function hasRoom(Location $location, CarbonInterface $slot): bool
    {
        $capacity = $this->capacityFor($location);

        if ($capacity === null) {
            return true;
        }

        return $this->occupying($location)->where('scheduled_for', $slot->copy()->utc())->count() < $capacity;
    }

    /**
     * The slots with no room left between two moments, keyed by the
     * location's local date: ['2026-11-02' => ['08:00:00', '08:30:00']].
     *
     * @return array<string, list<string>>
     */
    public function fullSlots(Location $location, CarbonInterface $from, CarbonInterface $to): array
    {
        $capacity = $this->capacityFor($location);

        if ($capacity === null) {
            return [];
        }

        $full = [];

        $this->occupying($location)
            ->whereBetween('scheduled_for', [$from->copy()->utc(), $to->copy()->utc()])
            ->groupBy('scheduled_for')
            ->havingRaw('count(*) >= ?', [$capacity])
            ->orderBy('scheduled_for')
            ->pluck('scheduled_for')
            ->each(function (CarbonInterface|string $scheduledFor) use ($location, &$full): void {
                $local = Date::parse($scheduledFor)->setTimezone($location->timezone);
                $full[$local->toDateString()][] = $local->format('H:i:s');
            });

        return $full;
    }

    /**
     * @return Builder<Appointment>
     */
    private function occupying(Location $location): Builder
    {
        return Appointment::query()
            ->whereBelongsTo($location)
            ->whereIn('status', self::OCCUPYING_STATUSES);
    }
}
