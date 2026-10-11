<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

/**
 * The validation messages for a check-in's driver, load and equipment fields.
 */
trait CheckInFieldMessages
{
    /**
     * Shared by the driver's check-in form and the dashboard edit, so both
     * report a bad value the same way.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer.required' => __('messages.checkInForm.customerRequired'),
            'customer.min' => __('messages.checkInForm.customerMin'),
            'customer.max' => __('messages.checkInForm.customerMax'),
            'destination_city.required' => __('messages.checkInForm.destinationCityRequired'),
            'destination_city.min' => __('messages.checkInForm.destinationCityMin'),
            'destination_state.required' => __('messages.checkInForm.destinationStateRequired'),
            'destination_state.min' => __('messages.checkInForm.destinationStateMin'),
            'destination_country.required' => __('messages.checkInForm.destinationCountryRequired'),
            'destination_country.size' => __('messages.checkInForm.destinationCountryRequired'),
            'destination_country.alpha' => __('messages.checkInForm.destinationCountryRequired'),
            'po_numbers.required' => __('messages.purchaseOrders.minOne'),
            'po_numbers.min' => __('messages.purchaseOrders.minOne'),
            'po_numbers.*.required' => __('messages.purchaseOrders.required'),
            'po_numbers.*.regex' => __('messages.purchaseOrders.format'),
            'po_numbers.*.distinct' => __('messages.purchaseOrders.duplicate'),
            'truck_name.required' => __('messages.checkInForm.truckNameRequired'),
            'truck_name.max' => __('messages.checkInForm.truckNameMax'),
            'truck_plate.required' => __('messages.checkInForm.truckPlateRequired'),
            'truck_plate.regex' => __('messages.checkInForm.plateInvalid'),
            'truck_plate_state.required' => __('messages.checkInForm.stateRequired'),
            'truck_plate_state.min' => __('messages.checkInForm.stateMin'),
            'truck_plate_state.max' => __('messages.checkInForm.stateMax'),
            'truck_plate_country.required' => __('messages.checkInForm.countryCodeRequired'),
            'truck_plate_country.size' => __('messages.checkInForm.countryCodeInvalid'),
            'truck_plate_country.alpha' => __('messages.checkInForm.countryCodeInvalid'),
            'truck_color.required' => __('messages.checkInForm.truckColorRequired'),
            'trailer_plate.required' => __('messages.checkInForm.trailerPlateRequired'),
            'trailer_plate.regex' => __('messages.checkInForm.plateInvalid'),
            'trailer_plate_state.required' => __('messages.checkInForm.stateRequired'),
            'trailer_plate_state.min' => __('messages.checkInForm.stateMin'),
            'trailer_plate_state.max' => __('messages.checkInForm.stateMax'),
            'trailer_plate_country.required' => __('messages.checkInForm.countryCodeRequired'),
            'trailer_plate_country.size' => __('messages.checkInForm.countryCodeInvalid'),
            'trailer_plate_country.alpha' => __('messages.checkInForm.countryCodeInvalid'),
            'empty_weight_lbs.required' => __('messages.checkInForm.emptyWeightRequired'),
            'empty_weight_lbs.integer' => __('messages.checkInForm.emptyWeightInvalid'),
            'empty_weight_lbs.min' => __('messages.checkInForm.emptyWeightInvalid'),
            'empty_weight_lbs.max' => __('messages.checkInForm.emptyWeightInvalid'),
            'drivers_name.required' => __('messages.checkInForm.driversNameRequired'),
            'drivers_name.min' => __('messages.checkInForm.driversNameMin'),
            'drivers_name.max' => __('messages.checkInForm.driversNameMax'),
            'drivers_cellphone.required' => __('messages.checkInForm.cellphoneRequired'),
            'drivers_cellphone.regex' => __('messages.checkInForm.cellphoneInvalid'),
            'drivers_cellphone.min' => __('messages.checkInForm.cellphoneInvalid'),
            'drivers_cellphone.max' => __('messages.checkInForm.cellphoneInvalid'),
            'drivers_license_number.required' => __('messages.checkInForm.licenseNumberRequired'),
            'drivers_license_number.min' => __('messages.checkInForm.licenseNumberInvalid'),
            'drivers_license_number.max' => __('messages.checkInForm.licenseNumberInvalid'),
            'drivers_license_state.required' => __('messages.checkInForm.stateRequired'),
            'drivers_license_state.min' => __('messages.checkInForm.stateMin'),
            'drivers_license_state.max' => __('messages.checkInForm.stateMax'),
            'drivers_license_country.required' => __('messages.checkInForm.countryCodeRequired'),
            'drivers_license_country.size' => __('messages.checkInForm.countryCodeInvalid'),
            'drivers_license_country.alpha' => __('messages.checkInForm.countryCodeInvalid'),
            'drivers_license_expiration_date.required' => __('messages.checkInForm.licenseExpirationRequired'),
            'drivers_license_expiration_date.date' => __('messages.checkInForm.licenseExpirationInvalid'),
            'drivers_license_expiration_date.date_format' => __('messages.checkInForm.licenseExpirationInvalid'),
            'drivers_license_expiration_date.after' => __('messages.checkInForm.licenseExpired'),
            'drivers_license_expiration_date.before' => __('messages.checkInForm.licenseExpirationTooFar'),
            'loading_instructions.max' => __('messages.checkInForm.loadingInstructionsMax'),
        ];
    }
}
