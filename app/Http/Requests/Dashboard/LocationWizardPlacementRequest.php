<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\Dashboard\LocationTimezones;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The add location wizard's map step: the timezone, suggested from the
 * geocoded address, and how far from the dock a driver may check in.
 */
#[FailOnUnknownFields]
final class LocationWizardPlacementRequest extends FormRequest
{
    /**
     * Who may add a location is enforced by the route's permission middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(LocationTimezones $timezones): array
    {
        return [
            'timezone' => ['required', 'string', Rule::in($timezones->for())],
            'max_distance_allowed' => ['required', 'integer', 'min:1', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'timezone.required' => 'Choose a timezone.',
            'timezone.in' => 'Choose a timezone from the list.',
        ];
    }
}
