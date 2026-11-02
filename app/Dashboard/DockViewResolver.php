<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Appointment\AppointmentScheduleResolver;
use App\Appointment\AppointmentSlotCapacity;
use App\Models\Appointment;
use App\Models\Dock;
use App\Models\Location;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;

/**
 * Shapes one location's day for the dock view, matching the DockViewDay and
 * DockViewDock contracts in resources/js/components/dashboard/types.ts.
 */
final readonly class DockViewResolver
{
    /** The hours drawn on a closed day with no bookings to size the grid by. */
    private const int FALLBACK_START_MINUTE = 8 * 60;

    private const int FALLBACK_END_MINUTE = 17 * 60;

    public function __construct(
        private AppointmentScheduleResolver $schedule,
        private AppointmentRecordResolver $records,
        private LocationDockResolver $docks,
    ) {}

    /**
     * Minutes count from the local midnight of the date. The drawn range covers
     * the opening hours and any booking outside them.
     *
     * @return array{date: string, today: string, timezone: string, isClosed: bool, slotIntervalMinutes: int, opensAtMinute: int|null, closesAtMinute: int|null, startMinute: int, endMinute: int}
     */
    public function day(Location $location, string $date): array
    {
        $opensAt = $this->schedule->opensAt($location, $date);
        $closesAt = $this->schedule->closesAt($location, $date);
        $midnight = Date::parse($date, $location->timezone)->startOfDay();

        $opensAtMinute = $opensAt instanceof CarbonInterface ? (int) $midnight->diffInMinutes($opensAt) : null;
        $closesAtMinute = $closesAt instanceof CarbonInterface ? (int) $midnight->diffInMinutes($closesAt->copy()->addSecond()->startOfMinute()) : null;
        $isClosed = $opensAtMinute === null || $closesAtMinute === null;

        $minutes = $this->bookings($location, $date)->flatMap(fn (Appointment $appointment): array => [
            max(0, (int) $midnight->diffInMinutes($appointment->scheduled_for)),
            min(24 * 60, (int) $midnight->diffInMinutes($appointment->ends_at)),
        ]);

        $start = min($minutes->min() ?? PHP_INT_MAX, $opensAtMinute ?? self::FALLBACK_START_MINUTE);
        $end = max($minutes->max() ?? 0, $closesAtMinute ?? self::FALLBACK_END_MINUTE);

        return [
            'date' => $date,
            'today' => Date::now($location->timezone)->toDateString(),
            'timezone' => $location->timezone,
            'isClosed' => $isClosed,
            'slotIntervalMinutes' => $location->appointmentSlotIntervalMinutes(),
            'opensAtMinute' => $opensAtMinute,
            'closesAtMinute' => $closesAtMinute,
            'startMinute' => (int) (floor($start / 60) * 60),
            'endMinute' => min(24 * 60, (int) (ceil($end / 60) * 60)),
        ];
    }

    /**
     * Docks that take appointments, plus any closed dock still holding a booking
     * that day, which staff can move bookings off but not onto.
     *
     * @return list<array{id: string, name: string, label: string, building: string|null, isOpen: bool}>
     */
    public function columns(Location $location, string $date): array
    {
        $heldDockIds = $this->bookings($location, $date)->pluck('dock_id')->filter()->unique()->all();

        $docks = Dock::query()
            ->whereBelongsTo($location)
            ->where(fn ($query) => $query
                ->where(fn ($open) => $open->where('is_active', true)->where('accepts_appointments', true))
                ->orWhereIn('id', $heldDockIds))
            ->with('building')
            ->get();

        return array_values($this->docks->naturalOrder($docks->all())
            ->map(fn (Dock $dock): array => [
                'id' => $dock->uuid,
                'name' => $dock->name,
                'label' => $dock->label(),
                'building' => $dock->building?->name,
                'isOpen' => $dock->is_active && $dock->accepts_appointments,
            ])
            ->all());
    }

    /**
     * The bookings still holding a dock on that local date.
     *
     * @return list<array<string, mixed>>
     */
    public function appointments(Location $location, string $date): array
    {
        return array_values($this->bookings($location, $date)
            ->map($this->records->record(...))
            ->all());
    }

    /**
     * @return Collection<int, Appointment>
     */
    private function bookings(Location $location, string $date): Collection
    {
        $midnight = Date::parse($date, $location->timezone)->startOfDay();

        return Appointment::query()
            ->whereBelongsTo($location)
            ->with(AppointmentRecordResolver::RELATIONS)
            ->whereIn('status', AppointmentSlotCapacity::OCCUPYING_STATUSES)
            ->where('scheduled_for', '<', $midnight->copy()->addDay()->utc())
            ->where('ends_at', '>', $midnight->copy()->utc())
            ->orderBy('scheduled_for')
            ->get();
    }
}
