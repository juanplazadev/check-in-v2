<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The optional fields a location can show on its check-in form. Each value is
 * the flag's key under the location's `checkin` config; a missing flag is
 * hidden. A shown field is required.
 */
enum CheckInFormField: string
{
    case TruckColor = 'show_truck_color';
    case EmptyWeightLbs = 'show_empty_weight_lbs';
    case TruckPlateState = 'show_truck_plate_state';
    case TruckPlateCountry = 'show_truck_plate_country';
    case TrailerPlateState = 'show_trailer_plate_state';
    case TrailerPlateCountry = 'show_trailer_plate_country';
    case DriversLicenseState = 'show_drivers_license_state';
    case DriversLicenseCountry = 'show_drivers_license_country';
    case DriversLicenseExpirationDate = 'show_drivers_license_expiration_date';

    /**
     * The flag as {@see \App\Models\Location::checkInFormFields()} keys it, e.g. "showTruckColor".
     */
    public function flag(): string
    {
        return 'show'.$this->name;
    }

    /**
     * The flag's path in the location's config, e.g. "checkin.show_truck_color".
     */
    public function configKey(): string
    {
        return 'checkin.'.$this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::TruckColor => 'Truck color field',
            self::EmptyWeightLbs => 'Empty weight field',
            self::TruckPlateState => 'Truck plate state field',
            self::TruckPlateCountry => 'Truck plate country field',
            self::TrailerPlateState => 'Trailer plate state field',
            self::TrailerPlateCountry => 'Trailer plate country field',
            self::DriversLicenseState => "Driver's license state field",
            self::DriversLicenseCountry => "Driver's license country field",
            self::DriversLicenseExpirationDate => "Driver's license expiration field",
        };
    }
}
