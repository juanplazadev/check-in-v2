<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\Enums\TrailerChute;
use App\Http\Requests\Concerns\CheckInFieldMessages;
use App\Models\CheckIn;
use App\Models\User;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * A staff edit of a check-in's driver, load and equipment details. The rules
 * mirror the driver's check-in form, so an edit can never store a value the
 * driver could not have submitted.
 *
 * Any field outside these rules fails the whole request, so a tampered payload
 * cannot reach a column the edit does not cover, such as status or location.
 */
#[FailOnUnknownFields]
final class UpdateCheckInRequest extends FormRequest
{
    use CheckInFieldMessages;

    /**
     * The check-in being edited, scoped like the controller's lookup. Null
     * when it is not visible, which the controller then answers with a 404.
     */
    public ?CheckIn $checkIn = null {
        get {
            $user = $this->user();

            return $this->checkIn ??= $user instanceof User
                ? CheckIn::query()->visibleTo($user)->with('location')->where('uuid', (string) $this->route('uuid'))->first()
                : null;
        }
    }

    /**
     * Staff-only is enforced by the route's permission middleware, and which
     * check-ins the user may see by the controller's visibleTo lookup.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Optional fields follow the location's form config, as on the driver's
     * form: hidden fields are excluded, yet stay known to the unknown field
     * check. A blank license number keeps the current one, since staff only
     * ever see its last four digits. The expiration date may be in the past,
     * as staff can be correcting an old record.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $fields = $this->checkIn?->location->checkInFormFields() ?? [];

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
            'drivers_email' => ['nullable', 'string', 'email', 'max:255'],
            'drivers_license_number' => ['nullable', 'string', 'min:4', 'max:20'],
            'drivers_license_state' => ($fields['showDriversLicenseState'] ?? false)
                ? ['required', 'string', 'min:2', 'max:30']
                : ['exclude'],
            'drivers_license_country' => ($fields['showDriversLicenseCountry'] ?? false)
                ? ['required', 'string', 'size:2', 'alpha']
                : ['exclude'],
            'drivers_license_expiration_date' => ($fields['showDriversLicenseExpirationDate'] ?? false)
                ? ['required', 'date', 'date_format:Y-m-d', 'before:+60 years']
                : ['exclude'],
            'loading_instructions' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * A check-in the user may not see is a 404 before any validation, so a
     * bad payload cannot tell a hidden check-in apart from a missing one.
     * Only keys the client sent are normalized, so nothing is merged in that
     * the unknown field check would then have to allow.
     */
    protected function prepareForValidation(): void
    {
        abort_unless($this->checkIn instanceof CheckIn, 404);

        $normalized = [];

        if ($this->has('drivers_cellphone')) {
            $digits = (string) preg_replace('/\D/', '', (string) $this->input('drivers_cellphone'));

            // The form shows the stored +1 number; drop that country code before re-adding it.
            if (mb_strlen($digits) === 11 && str_starts_with($digits, '1')) {
                $digits = mb_substr($digits, 1);
            }

            $normalized['drivers_cellphone'] = '+1'.$digits;
        }

        foreach (['truck_plate', 'truck_plate_country', 'trailer_plate', 'trailer_plate_country', 'drivers_license_country', 'destination_country'] as $key) {
            if ($this->has($key)) {
                $normalized[$key] = Str::upper((string) $this->input($key));
            }
        }

        if (is_array($this->input('po_numbers'))) {
            $normalized['po_numbers'] = array_map(
                fn (mixed $number): mixed => is_string($number) ? Str::upper(mb_trim($number)) : $number,
                $this->input('po_numbers'),
            );
        }

        $this->merge($normalized);
    }
}
