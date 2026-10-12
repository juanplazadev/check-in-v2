<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Enums\AppointmentColumn;
use App\Enums\AppointmentDateField;
use App\Models\Appointment;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * The headers and cells of the appointment exports, built the same way as
 * {@see CheckInSpreadsheet}.
 *
 * The current view follows the table: the same column labels as COLUMN_LABELS
 * in resources/js/pages/Dashboard/Appointments/Index.tsx, one cell per column,
 * and a date column that shows whichever date the table is filtering on. The
 * master export has a fixed set with every stored field staff work with, one
 * per cell. Times are in the requester's timezone.
 */
final readonly class AppointmentSpreadsheet
{
    /**
     * Callers eager load these, like {@see AppointmentRecordResolver::RELATIONS}.
     *
     * @var list<string>
     */
    public const array RELATIONS = AppointmentRecordResolver::RELATIONS;

    private const string DATE_TIME_FORMAT = 'Y-m-d H:i';

    /**
     * @param  list<string>  $columns  {@see AppointmentColumn} values, in display order.
     * @return list<string>
     */
    public function viewHeaders(array $columns, AppointmentDateField $dateField): array
    {
        return array_map(
            fn (string $column): string => $this->viewHeader(AppointmentColumn::from($column), $dateField),
            $columns,
        );
    }

    /**
     * @param  list<string>  $columns  {@see AppointmentColumn} values, in display order.
     * @return list<string|null>
     */
    public function viewRow(Appointment $appointment, array $columns, AppointmentDateField $dateField, string $timezone): array
    {
        return array_map(
            fn (string $column): ?string => $this->viewCell($appointment, AppointmentColumn::from($column), $dateField, $timezone),
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
            'Location',
            'Driver',
            'Cellphone',
            'Has account',
            'Locale',
            'Purchase orders',
            'Scheduled for',
            'Booked at',
            'Check-in',
            'Cancelled at',
            'Cancelation reason',
            'Archived at',
        ];
    }

    /**
     * In {@see self::masterHeaders()} order.
     *
     * @return list<string|null>
     */
    public function masterRow(Appointment $appointment, string $timezone): array
    {
        return [
            $appointment->reference_number,
            $appointment->uuid,
            Str::headline($appointment->status->value),
            $appointment->location->name,
            $appointment->drivers_name,
            $appointment->drivers_cellphone,
            $appointment->user_id === null ? 'No' : 'Yes',
            $appointment->locale,
            $this->purchaseOrders($appointment),
            $this->dateTime($appointment->scheduled_for, $timezone),
            $this->dateTime($appointment->created_at, $timezone),
            $appointment->checkIn?->reference_number,
            $this->dateTime($appointment->cancelled_at, $timezone),
            $appointment->cancelled_reason,
            $this->dateTime($appointment->deleted_at, $timezone),
        ];
    }

    private function viewHeader(AppointmentColumn $column, AppointmentDateField $dateField): string
    {
        return match ($column) {
            AppointmentColumn::Reference => 'Reference',
            AppointmentColumn::Date => match ($dateField) {
                AppointmentDateField::Scheduled => 'Date (Scheduled)',
                AppointmentDateField::Booked => 'Date (Booked)',
            },
            AppointmentColumn::Driver => 'Driver',
            AppointmentColumn::Cellphone => 'Cellphone',
            AppointmentColumn::Location => 'Location',
            AppointmentColumn::PurchaseOrders => 'Purchase orders',
            AppointmentColumn::Status => 'Status',
            AppointmentColumn::CheckIn => 'Check-in',
        };
    }

    private function viewCell(Appointment $appointment, AppointmentColumn $column, AppointmentDateField $dateField, string $timezone): ?string
    {
        return match ($column) {
            AppointmentColumn::Reference => $appointment->reference_number,
            AppointmentColumn::Date => $this->dateTime($appointment->{$dateField->column()}, $timezone),
            AppointmentColumn::Driver => $appointment->drivers_name,
            AppointmentColumn::Cellphone => $appointment->drivers_cellphone,
            AppointmentColumn::Location => $appointment->location->name,
            AppointmentColumn::PurchaseOrders => $this->purchaseOrders($appointment),
            AppointmentColumn::Status => Str::headline($appointment->status->value),
            AppointmentColumn::CheckIn => $appointment->checkIn?->reference_number,
        };
    }

    private function purchaseOrders(Appointment $appointment): string
    {
        return $appointment->purchaseOrders->pluck('number')->implode(', ');
    }

    private function dateTime(?CarbonInterface $moment, string $timezone): ?string
    {
        return $moment?->copy()->setTimezone($timezone)->format(self::DATE_TIME_FORMAT);
    }
}
