<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Enums\TrailerChute;
use App\Models\CheckIn;
use App\Models\RecordHistory;

/**
 * Shapes a check-in into the CheckInRecord contract in
 * resources/js/components/dashboard/types.ts.
 *
 * One shape for the live queue, the table, the detail page and the broadcast
 * payload, so the browser can drop a pushed record straight into any of them.
 */
final readonly class CheckInRecordResolver
{
    /**
     * Everything {@see self::record()} reads, for eager loading at the call site.
     *
     * @var list<string>
     */
    public const array RELATIONS = ['location', 'purchaseOrders', 'appointment', 'user'];

    public function __construct(private RecordHistoryEntryResolver $history) {}

    /**
     * @return array<string, mixed>
     */
    public function record(CheckIn $checkIn): array
    {
        $checkIn->loadMissing(self::RELATIONS);

        /** @var list<string> $purchaseOrders */
        $purchaseOrders = $checkIn->purchaseOrders->pluck('number')->all();

        return [
            'uuid' => $checkIn->uuid,
            'referenceNumber' => $checkIn->reference_number,
            'status' => $checkIn->status->value,
            'erpStatus' => $checkIn->erp_status->value,
            'location' => [
                'id' => $checkIn->location->id,
                'uuid' => $checkIn->location->uuid,
                'name' => $checkIn->location->name,
            ],
            'customer' => $checkIn->customer,
            'driver' => [
                'name' => $checkIn->drivers_name,
                'cellphone' => $checkIn->drivers_cellphone,
                'email' => $checkIn->drivers_email,
                'licenseState' => $checkIn->drivers_license_state ?? '',
                'licenseCountry' => $checkIn->drivers_license_country ?? '',
                'licenseExpirationDate' => $checkIn->drivers_license_expiration_date,
                'licenseLastFour' => mb_substr((string) $checkIn->drivers_license_number, -4),
                'hasAccount' => $checkIn->user_id !== null,
                'userUuid' => $checkIn->user?->uuid,
            ],
            'truck' => [
                'name' => $checkIn->truck_name,
                'plate' => $checkIn->truck_plate,
                'plateState' => $checkIn->truck_plate_state ?? '',
                'plateCountry' => $checkIn->truck_plate_country ?? '',
                'color' => $checkIn->truck_color ?? '',
            ],
            'trailer' => [
                'plate' => $checkIn->trailer_plate,
                'plateState' => $checkIn->trailer_plate_state ?? '',
                'plateCountry' => $checkIn->trailer_plate_country ?? '',
                'chute' => ($checkIn->trailer_chute ?? TrailerChute::NotApplicable)->value,
            ],
            'emptyWeightLbs' => $checkIn->empty_weight_lbs === null ? null : (int) $checkIn->empty_weight_lbs,
            'destination' => [
                'city' => $checkIn->destination_city,
                'state' => $checkIn->destination_state,
                'country' => $checkIn->destination_country,
            ],
            'purchaseOrders' => $purchaseOrders,
            'loadingInstructions' => $checkIn->loading_instructions,
            'appointmentReference' => $checkIn->appointment?->reference_number,
            'locale' => $checkIn->locale,
            'createdAt' => $checkIn->created_at->toIso8601String(),
            'completedAt' => $checkIn->completed_at?->toIso8601String(),
            'cancelledAt' => $checkIn->cancelled_at?->toIso8601String(),
            'cancelledReason' => $checkIn->cancelled_reason,
            'erpSyncedAt' => $checkIn->erp_synced_at?->toIso8601String(),
            'archivedAt' => $checkIn->deleted_at?->toIso8601String(),
            'history' => $checkIn->relationLoaded('history')
                ? $checkIn->history->map(fn (RecordHistory $entry): array => $this->history->entry($entry, 'Check-in submitted'))->all()
                : [],
        ];
    }
}
