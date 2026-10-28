<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The columns a staff member may show on the dashboard check-in table. Mirrored
 * in resources/js/pages/Dashboard/CheckIns/Index.tsx.
 *
 * Columns backed by an optional check-in form field are offered only when one
 * of the user's locations collects that field; see {@see self::formFieldFlags()}.
 * A region column shows a stored state and country pair as one cell.
 */
enum CheckInColumn: string
{
    case Reference = 'reference';
    case Driver = 'driver';
    case Customer = 'customer';
    case Location = 'location';
    case Equipment = 'equipment';
    case ErpStatus = 'erpStatus';
    case Status = 'status';
    case Date = 'date';
    case Cellphone = 'cellphone';
    case TruckName = 'truckName';
    case TrailerChute = 'trailerChute';
    case Destination = 'destination';
    case PurchaseOrders = 'purchaseOrders';
    case AppointmentReference = 'appointmentReference';
    case TruckColor = 'truckColor';
    case EmptyWeight = 'emptyWeight';
    case TruckPlateRegion = 'truckPlateRegion';
    case TrailerPlateRegion = 'trailerPlateRegion';
    case LicenseRegion = 'licenseRegion';
    case LicenseExpiration = 'licenseExpiration';
    case Dock = 'dock';

    /**
     * The {@see \App\Models\Location::checkInFormFields()} flags behind this
     * column. It is offered when any of them is on at some location, or always
     * when the list is empty because every check-in has the field.
     *
     * @return list<string>
     */
    public function formFieldFlags(): array
    {
        return match ($this) {
            self::TruckColor => ['showTruckColor'],
            self::EmptyWeight => ['showEmptyWeightLbs'],
            self::TruckPlateRegion => ['showTruckPlateState', 'showTruckPlateCountry'],
            self::TrailerPlateRegion => ['showTrailerPlateState', 'showTrailerPlateCountry'],
            self::LicenseRegion => ['showDriversLicenseState', 'showDriversLicenseCountry'],
            self::LicenseExpiration => ['showDriversLicenseExpirationDate'],
            default => [],
        };
    }

    /**
     * The reference links to the record, so it is always shown.
     */
    public function isHideable(): bool
    {
        return $this !== self::Reference;
    }
}
