<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Appointment\AppointmentSlotCapacity;
use App\Enums\AppointmentStatus;
use App\Enums\CheckInStatus;
use App\Models\Appointment;
use App\Models\Building;
use App\Models\CheckIn;
use App\Models\Dock;
use App\Models\Location;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

/**
 * Shapes a location's buildings and docks into the LocationDocks,
 * AppointmentDockOption and CheckInDoorOption contracts in resources/js/components/dashboard/types.ts.
 * Mapped by hand because id is hidden on the models.
 */
final readonly class LocationDockResolver
{
    public function __construct(private AppointmentSlotCapacity $capacity) {}

    /**
     * The docks page: every building and dock, and how many bookings a slot takes.
     *
     * @return array{capacity: int|null, buildings: list<array{id: string, name: string}>, docks: list<array{id: string, name: string, label: string, buildingId: string|null, isActive: bool, acceptsAppointments: bool, upcomingCount: int, hasRecords: bool}>}
     */
    public function docks(Location $location): array
    {
        $buildings = $location->buildings()->get();

        $docks = $location->docks()
            ->with('building')
            ->withCount([
                'appointments as upcoming_count' => fn ($query) => $query
                    ->withoutTrashed()
                    ->where('status', AppointmentStatus::Scheduled)
                    ->where('scheduled_for', '>=', Date::now()),
                'appointments as appointment_count',
                'checkIns as check_in_count',
            ])
            ->get();

        return [
            'capacity' => $this->capacity->capacityFor($location),
            'buildings' => $buildings
                ->map(fn (Building $building): array => ['id' => $building->uuid, 'name' => $building->name])
                ->values()
                ->all(),
            'docks' => $this->naturalOrder($docks->all())
                ->map(fn (Dock $dock): array => [
                    'id' => $dock->uuid,
                    'name' => $dock->name,
                    'label' => $dock->label(),
                    'buildingId' => $dock->building?->uuid,
                    'isActive' => $dock->is_active,
                    'acceptsAppointments' => $dock->accepts_appointments,
                    'upcomingCount' => (int) $dock->getAttribute('upcoming_count'),
                    'hasRecords' => (int) $dock->getAttribute('appointment_count') + (int) $dock->getAttribute('check_in_count') > 0,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * The dock picker on an appointment's page: the location's bookable
     * docks, with those another booking holds at the same time marked taken.
     *
     * @return list<array{id: string, label: string, building: string|null, takenBy: string|null}>
     */
    public function optionsFor(Appointment $appointment): array
    {
        $docks = Dock::query()
            ->where('location_id', $appointment->location_id)
            ->bookable()
            ->with('building')
            ->get();

        $taken = Appointment::query()
            ->where('location_id', $appointment->location_id)
            ->where('scheduled_for', $appointment->scheduled_for)
            ->whereKeyNot($appointment->id)
            ->whereNotNull('dock_id')
            ->whereIn('status', AppointmentSlotCapacity::OCCUPYING_STATUSES)
            ->pluck('reference_number', 'dock_id');

        return $this->naturalOrder($docks->all())
            ->map(fn (Dock $dock): array => [
                'id' => $dock->uuid,
                'label' => $dock->label(),
                'building' => $dock->building?->name,
                'takenBy' => $taken[$dock->id] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * The door picker for check-ins, by location uuid: every active door, the
     * walk-in-only ones too, with the pending check-in using it, if any.
     *
     * @param  list<int>|null  $locationIds  Null for every location.
     * @return array<string, list<array{id: string, label: string, building: string|null, inUseBy: string|null}>>
     */
    public function doorOptions(?array $locationIds): array
    {
        $docks = Dock::query()
            ->where('is_active', true)
            ->when($locationIds !== null, fn ($query) => $query->whereIn('location_id', $locationIds))
            ->with(['building', 'location:id,uuid'])
            ->get();

        $inUse = CheckIn::query()
            ->whereIn('dock_id', $docks->modelKeys())
            ->where('status', CheckInStatus::Pending)
            ->pluck('reference_number', 'dock_id');

        $options = [];

        foreach ($this->naturalOrder($docks->all()) as $dock) {
            $options[$dock->location->uuid][] = [
                'id' => $dock->uuid,
                'label' => $dock->label(),
                'building' => $dock->building?->name,
                'inUseBy' => $inUse[$dock->id] ?? null,
            ];
        }

        return $options;
    }

    /**
     * Grouped by building, then "Door 2" before "Door 10".
     *
     * @param  list<Dock>  $docks
     * @return Collection<int, Dock>
     */
    private function naturalOrder(array $docks): Collection
    {
        usort($docks, fn (Dock $a, Dock $b): int => [
            $a->building->sort_order ?? PHP_INT_MAX,
            $a->building->name ?? '',
            $a->sort_order,
        ] <=> [
            $b->building->sort_order ?? PHP_INT_MAX,
            $b->building->name ?? '',
            $b->sort_order,
        ] ?: strnatcasecmp($a->name, $b->name));

        return collect($docks);
    }
}
