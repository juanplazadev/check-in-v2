<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\CheckIn\CheckInAvailabilityResolver;
use App\DTOs\CheckInLocationDTO;
use App\Enums\TrailerChute;
use App\Http\Requests\Concerns\CheckInFieldMessages;
use App\Models\Location;
use App\Queries\CheckInLocation;
use App\Session\CheckInSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class CheckInFormRequest extends FormRequest
{
    use CheckInFieldMessages;

    public ?Location $location = null {
        get {
            return $this->location ??= resolve(CheckInLocation::class)->execute((string) $this->route('uuid'));
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Optional fields are only validated when the location's config shows
     * them on the form; hidden fields are excluded from the validated data.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $fields = $this->location?->checkInFormFields() ?? [];

        return [
            'customer' => ['required', 'string', 'min:2', 'max:40'],
            'destination_city' => ['required', 'string', 'min:2', 'max:30'],
            'destination_state' => ['required', 'string', 'min:2', 'max:30'],
            'destination_country' => ['required', 'string', 'size:2', 'alpha'],
            'po_numbers' => ['required', 'array', 'min:1', 'max:10'],
            'po_numbers.*' => ['required', 'string', 'distinct:ignore_case', 'min:7', 'max:13', 'regex:/^(SO|PO|PU)-\d{4,10}$/i'],
            'truck_name' => ['required', 'string', 'min:1', 'max:30'],
            'truck_plate' => ['required', 'string', 'regex:/^[A-Z0-9 -]{2,10}$/'],
            'truck_plate_state' => ($fields['showTruckPlateState'] ?? false)
                ? ['required', 'string', 'min:2', 'max:30']
                : ['exclude'],
            'truck_plate_country' => ($fields['showTruckPlateCountry'] ?? false)
                ? ['required', 'string', 'size:2', 'alpha']
                : ['exclude'],
            'truck_color' => ($fields['showTruckColor'] ?? false)
                ? ['required', 'string', Rule::in((array) config('app.truck_colors'))]
                : ['exclude'],
            'trailer_plate' => ['required', 'string', 'regex:/^[A-Z0-9 -]{2,10}$/'],
            'trailer_plate_state' => ($fields['showTrailerPlateState'] ?? false)
                ? ['required', 'string', 'min:2', 'max:30']
                : ['exclude'],
            'trailer_plate_country' => ($fields['showTrailerPlateCountry'] ?? false)
                ? ['required', 'string', 'size:2', 'alpha']
                : ['exclude'],
            'trailer_chute' => ['nullable', Rule::enum(TrailerChute::class)],
            'empty_weight_lbs' => ($fields['showEmptyWeightLbs'] ?? false)
                ? ['required', 'integer', 'min:1000', 'max:99999']
                : ['exclude'],
            'drivers_name' => ['required', 'string', 'min:2', 'max:28'],
            'drivers_cellphone' => ['required', 'string', 'min:12', 'max:12', 'regex:/^\+1\d{10}$/'],
            'drivers_license_number' => ['required', 'string', 'min:4', 'max:20'],
            'drivers_license_state' => ($fields['showDriversLicenseState'] ?? false)
                ? ['required', 'string', 'min:2', 'max:30']
                : ['exclude'],
            'drivers_license_country' => ($fields['showDriversLicenseCountry'] ?? false)
                ? ['required', 'string', 'size:2', 'alpha']
                : ['exclude'],
            'loading_instructions' => ['nullable', 'string', 'max:500'],
            'drivers_license_expiration_date' => ($fields['showDriversLicenseExpirationDate'] ?? false)
                ? ['required', 'date', 'date_format:Y-m-d', 'after:today', 'before:+60 years']
                : ['exclude'],
        ];
    }

    /**
     * The gate-pass middleware guarantees a fresh pass, but the location could
     * have been deactivated between the gate and the submit.
     *
     * @return array<int, callable>
     */
    public function after(
        CheckInAvailabilityResolver $availabilityResolver,
        CheckInSession $session,
    ): array {
        return [
            function (Validator $validator) use ($availabilityResolver, $session): void {
                $context = $session->getLocation();

                $location = $context instanceof CheckInLocationDTO
                    ? resolve(CheckInLocation::class)->execute($context->id)
                    : null;

                if (! $location instanceof Location) {
                    $validator->errors()->add('uuid', __('messages.checkInSelectLocation.invalidLocation'));

                    return;
                }

                $userCoords = $session->getUserCoords();
                if ($userCoords === null) {
                    $validator->errors()->add('uuid', __('messages.checkInSelectLocation.invalidLocation'));

                    return;
                }

                $availability = $availabilityResolver->isAvailableForCheckIn($location, $userCoords);

                if (! $availability->allowed) {
                    $validator->errors()->add('uuid', $availability->reason ?? __('messages.checkInSelectLocation.invalidLocation'));
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'drivers_cellphone' => '+1'.preg_replace('/\D/', '', (string) $this->input('drivers_cellphone', '')),
            'truck_plate' => Str::upper((string) $this->input('truck_plate', '')),
            'truck_plate_country' => Str::upper((string) $this->input('truck_plate_country', '')),
            'trailer_plate' => Str::upper((string) $this->input('trailer_plate', '')),
            'trailer_plate_country' => Str::upper((string) $this->input('trailer_plate_country', '')),
            'drivers_license_country' => Str::upper((string) $this->input('drivers_license_country', '')),
        ]);
    }
}
