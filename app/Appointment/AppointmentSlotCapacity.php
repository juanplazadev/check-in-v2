<?php

declare(strict_types=1);

namespace App\Appointment;

use App\DTOs\AppointmentAvailabilityWindowDTO;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Dock;
use App\Models\Location;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

/**
 * How many bookings a location holds at once: one per active dock that
 * accepts appointments. A location with no such docks has no limit, as
 * before docks existed.
 *
 * A booking holds its dock from scheduled_for until ends_at, so a long load
 * fills every slot it runs through, not only the one it starts in.
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
     * Bookings at once, or null when the location has no limit.
     */
    public function capacityFor(Location $location): ?int
    {
        $docks = Dock::query()->whereBelongsTo($location)->bookable()->count();

        return $docks === 0 ? null : $docks;
    }

    /**
     * Whether a booking from $start to $end fits alongside the ones already
     * made, at every moment it runs.
     */
    public function hasRoom(Location $location, CarbonInterface $start, CarbonInterface $end): bool
    {
        $capacity = $this->capacityFor($location);

        if ($capacity === null) {
            return true;
        }

        $bookings = $this->overlapping($location, $start, $end)->map(fn (Appointment $appointment): array => [
            $appointment->scheduled_for->getTimestamp(),
            $appointment->ends_at->getTimestamp(),
        ]);

        return $this->peak($bookings, $start->getTimestamp(), $end->getTimestamp()) < $capacity;
    }

    /**
     * The start times in each window that have no room for a booking of each
     * length, keyed by length in minutes and then by the location's local
     * date: [90 => ['2026-11-02' => ['08:00:00', '08:30:00']]].
     *
     * @param  list<AppointmentAvailabilityWindowDTO>  $windows
     * @param  list<int>  $durations
     * @return array<int, array<string, list<string>>>
     */
    public function fullSlots(Location $location, array $windows, int $intervalMinutes, array $durations): array
    {
        $capacity = $this->capacityFor($location);

        if ($capacity === null || $windows === [] || $durations === [] || $intervalMinutes <= 0) {
            return [];
        }

        $slotsByDate = [];

        foreach ($windows as $window) {
            $first = Date::parse(sprintf('%s %s', $window->date, $window->firstSlot), $location->timezone);
            $last = Date::parse(sprintf('%s %s', $window->date, $window->lastSlot), $location->timezone);

            for ($slot = $first->copy(); $slot->lte($last); $slot = $slot->copy()->addMinutes($intervalMinutes)) {
                $slotsByDate[$window->date][] = $slot;
            }
        }

        $allSlots = array_merge(...array_values($slotsByDate));

        if ($allSlots === []) {
            return [];
        }

        // One query for the whole horizon; every slot is then checked in memory.
        $bookings = $this->overlapping(
            $location,
            $allSlots[0],
            end($allSlots)->copy()->addMinutes(max($durations)),
        )->map(fn (Appointment $appointment): array => [
            $appointment->scheduled_for->getTimestamp(),
            $appointment->ends_at->getTimestamp(),
        ]);

        $full = [];

        foreach (array_unique($durations) as $duration) {
            foreach ($slotsByDate as $date => $slots) {
                foreach ($slots as $slot) {
                    $start = $slot->getTimestamp();

                    if ($this->peak($bookings, $start, $start + $duration * 60) >= $capacity) {
                        $full[$duration][$date][] = $slot->format('H:i:s');
                    }
                }
            }
        }

        return $full;
    }

    /**
     * The most bookings running at the same moment between $start and $end.
     * The count only rises when a booking starts, so it is enough to check
     * $start itself and every booking start inside the range.
     *
     * @param  Collection<int, array{0: int, 1: int}>  $bookings  [start, end] as timestamps
     */
    private function peak(Collection $bookings, int $start, int $end): int
    {
        $inRange = $bookings->filter(fn (array $booking): bool => $booking[0] < $end && $booking[1] > $start);

        $moments = $inRange
            ->map(fn (array $booking): int => $booking[0])
            ->filter(fn (int $moment): bool => $moment > $start)
            ->push($start);

        return $moments
            ->map(fn (int $moment): int => $inRange->filter(fn (array $booking): bool => $booking[0] <= $moment && $booking[1] > $moment)->count())
            ->max() ?? 0;
    }

    /**
     * Bookings that still hold their dock at some point between $start and $end.
     * A booking that ends exactly as another starts does not overlap it.
     *
     * @return Collection<int, Appointment>
     */
    private function overlapping(Location $location, CarbonInterface $start, CarbonInterface $end): Collection
    {
        return $this->occupying($location)
            ->where('scheduled_for', '<', $end->copy()->utc())
            ->where('ends_at', '>', $start->copy()->utc())
            ->get(['scheduled_for', 'ends_at'])
            ->toBase();
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
