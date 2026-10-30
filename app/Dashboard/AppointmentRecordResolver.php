<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Models\Appointment;
use App\Models\RecordHistory;

/**
 * Shapes an appointment into the AppointmentRecord contract in
 * resources/js/components/dashboard/types.ts.
 */
final readonly class AppointmentRecordResolver
{
    /**
     * Everything {@see self::record()} reads, for eager loading at the call site.
     *
     * @var list<string>
     */
    public const array RELATIONS = ['location', 'purchaseOrders', 'checkIn', 'user', 'dock.building', 'loadType'];

    public function __construct(private RecordHistoryEntryResolver $history) {}

    /**
     * @return array<string, mixed>
     */
    public function record(Appointment $appointment): array
    {
        $appointment->loadMissing(self::RELATIONS);

        /** @var list<string> $purchaseOrders */
        $purchaseOrders = $appointment->purchaseOrders->pluck('number')->all();

        return [
            'uuid' => $appointment->uuid,
            'referenceNumber' => $appointment->reference_number,
            'status' => $appointment->status->value,
            'location' => [
                'id' => $appointment->location->id,
                'uuid' => $appointment->location->uuid,
                'name' => $appointment->location->name,
            ],
            'driver' => [
                'name' => $appointment->drivers_name,
                'cellphone' => $appointment->drivers_cellphone,
                'email' => $appointment->user?->email,
                'hasAccount' => $appointment->user_id !== null,
            ],
            'scheduledFor' => $appointment->scheduled_for->toIso8601String(),
            'endsAt' => $appointment->ends_at->toIso8601String(),
            'loadType' => $appointment->loadType?->name,
            'purchaseOrders' => $purchaseOrders,
            'dock' => $appointment->dock === null ? null : [
                'uuid' => $appointment->dock->uuid,
                'label' => $appointment->dock->label(),
            ],
            'cancelledReason' => $appointment->cancelled_reason,
            'checkIn' => $appointment->checkIn === null ? null : [
                'uuid' => $appointment->checkIn->uuid,
                'referenceNumber' => $appointment->checkIn->reference_number,
            ],
            'createdAt' => $appointment->created_at->toIso8601String(),
            'archivedAt' => $appointment->deleted_at?->toIso8601String(),
            'history' => $appointment->relationLoaded('history')
                ? $appointment->history->map(fn (RecordHistory $entry): array => $this->history->entry($entry, 'Appointment booked'))->all()
                : [],
        ];
    }
}
