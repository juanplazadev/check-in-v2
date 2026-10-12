<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Enums\CheckInColumn;
use App\Enums\CheckInDateField;
use App\Enums\TrailerChute;
use App\Models\CheckIn;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * The headers and cells of the check-in exports.
 *
 * The current view follows the table: the same column labels as COLUMN_LABELS
 * in resources/js/pages/Dashboard/CheckIns/Index.tsx, one cell per column, and
 * a date column that shows whichever date the table is filtering on. The
 * master export has a fixed set with every stored field, one per cell.
 *
 * Neither ever holds the full license number, only its last four digits, as
 * on the dashboard. Times are in the requester's timezone.
 */
final readonly class CheckInSpreadsheet
{
    /**
     * Callers eager load these, like {@see CheckInRecordResolver::RELATIONS}.
     *
     * @var list<string>
     */
    public const array RELATIONS = ['location', 'purchaseOrders', 'appointment'];

    private const string DATE_FORMAT = 'Y-m-d';

    private const string DATE_TIME_FORMAT = 'Y-m-d H:i';

    /**
     * @param  list<string>  $columns  {@see CheckInColumn} values, in display order.
     * @return list<string>
     */
    public function viewHeaders(array $columns, CheckInDateField $dateField): array
    {
        return array_map(
            fn (string $column): string => $this->viewHeader(CheckInColumn::from($column), $dateField),
            $columns,
        );
    }

    /**
     * @param  list<string>  $columns  {@see CheckInColumn} values, in display order.
     * @return list<string|int|null>
     */
    public function viewRow(CheckIn $checkIn, array $columns, CheckInDateField $dateField, string $timezone): array
    {
        return array_map(
            fn (string $column): string|int|null => $this->viewCell($checkIn, CheckInColumn::from($column), $dateField, $timezone),
            $columns,
        );
    }

    /**
     * @return list<string>
     */
    public function masterHeaders(): array
    {
        return [
            'Reference',
            'UUID',
            'Status',
            'ERP status',
            'Location',
            'Customer',
            'Driver',
            'Cellphone',
            'Email',
            'License (last 4)',
            'License state',
            'License country',
            'License expiration',
            'Has account',
            'Locale',
            'Truck',
            'Truck plate',
            'Truck plate state',
            'Truck plate country',
            'Truck color',
            'Trailer plate',
            'Trailer plate state',
            'Trailer plate country',
            'Chute',
            'Empty weight (lbs)',
            'Destination city',
            'Destination state',
            'Destination country',
            'Purchase orders',
            'Loading instructions',
            'Appointment',
            'Submitted at',
            'Completed at',
            'ERP synced at',
            'Archived at',
        ];
    }

    /**
     * In {@see self::masterHeaders()} order.
     *
     * @return list<string|int|null>
     */
    public function masterRow(CheckIn $checkIn, string $timezone): array
    {
        return [
            $checkIn->reference_number,
            $checkIn->uuid,
            Str::headline($checkIn->status->value),
            Str::headline($checkIn->erp_status->value),
            $checkIn->location->name,
            $checkIn->customer,
            $checkIn->drivers_name,
            $checkIn->drivers_cellphone,
            $checkIn->drivers_email,
            mb_substr((string) $checkIn->drivers_license_number, -4),
            $checkIn->drivers_license_state,
            $checkIn->drivers_license_country,
            $this->date($checkIn->drivers_license_expiration_date),
            $checkIn->user_id === null ? 'No' : 'Yes',
            $checkIn->locale,
            $checkIn->truck_name,
            $checkIn->truck_plate,
            $checkIn->truck_plate_state,
            $checkIn->truck_plate_country,
            $checkIn->truck_color === null ? null : Str::headline($checkIn->truck_color),
            $checkIn->trailer_plate,
            $checkIn->trailer_plate_state,
            $checkIn->trailer_plate_country,
            $this->chute($checkIn->trailer_chute),
            $checkIn->empty_weight_lbs === null ? null : (int) $checkIn->empty_weight_lbs,
            $checkIn->destination_city,
            $checkIn->destination_state,
            $checkIn->destination_country,
            $this->purchaseOrders($checkIn),
            $checkIn->loading_instructions,
            $checkIn->appointment?->reference_number,
            $this->dateTime($checkIn->created_at, $timezone),
            $this->dateTime($checkIn->completed_at, $timezone),
            $this->dateTime($checkIn->erp_synced_at, $timezone),
            $this->dateTime($checkIn->deleted_at, $timezone),
        ];
    }

    private function viewHeader(CheckInColumn $column, CheckInDateField $dateField): string
    {
        return match ($column) {
            CheckInColumn::Reference => 'Reference',
            CheckInColumn::Driver => 'Driver',
            CheckInColumn::Customer => 'Customer',
            CheckInColumn::Location => 'Location',
            CheckInColumn::Equipment => 'Truck / Trailer',
            CheckInColumn::ErpStatus => 'ERP',
            CheckInColumn::Status => 'Status',
            CheckInColumn::Date => match ($dateField) {
                CheckInDateField::Submitted => 'Date (Submitted)',
                CheckInDateField::Completed => 'Date (Completed)',
                CheckInDateField::ErpSynced => 'Date (ERP synced)',
            },
            CheckInColumn::Cellphone => 'Cellphone',
            CheckInColumn::TruckName => 'Truck',
            CheckInColumn::TrailerChute => 'Chute',
            CheckInColumn::Destination => 'Destination',
            CheckInColumn::PurchaseOrders => 'Purchase orders',
            CheckInColumn::AppointmentReference => 'Appointment',
            CheckInColumn::TruckColor => 'Truck color',
            CheckInColumn::EmptyWeight => 'Empty weight',
            CheckInColumn::TruckPlateRegion => 'Truck plate region',
            CheckInColumn::TrailerPlateRegion => 'Trailer plate region',
            CheckInColumn::LicenseRegion => 'License region',
            CheckInColumn::LicenseExpiration => 'License expiration',
        };
    }

    private function viewCell(CheckIn $checkIn, CheckInColumn $column, CheckInDateField $dateField, string $timezone): string|int|null
    {
        return match ($column) {
            CheckInColumn::Reference => $checkIn->reference_number,
            CheckInColumn::Driver => $checkIn->drivers_name,
            CheckInColumn::Customer => $checkIn->customer,
            CheckInColumn::Location => $checkIn->location->name,
            CheckInColumn::Equipment => $checkIn->truck_plate.' / '.$checkIn->trailer_plate,
            CheckInColumn::ErpStatus => Str::headline($checkIn->erp_status->value),
            CheckInColumn::Status => Str::headline($checkIn->status->value),
            CheckInColumn::Date => $this->dateTime($checkIn->{$dateField->column()}, $timezone),
            CheckInColumn::Cellphone => $checkIn->drivers_cellphone,
            CheckInColumn::TruckName => $checkIn->truck_name,
            CheckInColumn::TrailerChute => $this->chute($checkIn->trailer_chute),
            CheckInColumn::Destination => $this->joined($checkIn->destination_city, $checkIn->destination_state, $checkIn->destination_country),
            CheckInColumn::PurchaseOrders => $this->purchaseOrders($checkIn),
            CheckInColumn::AppointmentReference => $checkIn->appointment?->reference_number,
            CheckInColumn::TruckColor => $checkIn->truck_color === null ? null : Str::headline($checkIn->truck_color),
            CheckInColumn::EmptyWeight => $checkIn->empty_weight_lbs === null ? null : (int) $checkIn->empty_weight_lbs,
            CheckInColumn::TruckPlateRegion => $this->joined($checkIn->truck_plate_state, $checkIn->truck_plate_country),
            CheckInColumn::TrailerPlateRegion => $this->joined($checkIn->trailer_plate_state, $checkIn->trailer_plate_country),
            CheckInColumn::LicenseRegion => $this->joined($checkIn->drivers_license_state, $checkIn->drivers_license_country),
            CheckInColumn::LicenseExpiration => $this->date($checkIn->drivers_license_expiration_date),
        };
    }

    private function purchaseOrders(CheckIn $checkIn): string
    {
        return $checkIn->purchaseOrders->pluck('number')->implode(', ');
    }

    private function chute(?TrailerChute $chute): ?string
    {
        return match ($chute) {
            null => null,
            TrailerChute::NotApplicable => 'N/A',
            default => Str::headline($chute->value),
        };
    }

    /**
     * Stored as a date without a time, so no timezone applies.
     */
    private function date(CarbonInterface|string|null $date): ?string
    {
        if ($date === null) {
            return null;
        }

        return $date instanceof CarbonInterface ? $date->format(self::DATE_FORMAT) : mb_substr($date, 0, 10);
    }

    private function dateTime(?CarbonInterface $moment, string $timezone): ?string
    {
        return $moment?->copy()->setTimezone($timezone)->format(self::DATE_TIME_FORMAT);
    }

    private function joined(?string ...$parts): string
    {
        return implode(', ', array_filter($parts, fn (?string $part): bool => $part !== null && $part !== ''));
    }
}
