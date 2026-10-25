<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The add location wizard's features step: whether drivers can check in,
 * book appointments, or both. At least one is needed, since a location with
 * neither has nothing for drivers to do and no hours to set.
 */
#[FailOnUnknownFields]
final class LocationWizardFeaturesRequest extends FormRequest
{
    /**
     * Who may add a location is enforced by the route's permission middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'is_checkins_enabled' => ['required', 'boolean'],
            'is_appointments_enabled' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isEmpty() && ! $this->boolean('is_checkins_enabled') && ! $this->boolean('is_appointments_enabled')) {
                    $validator->errors()->add('is_checkins_enabled', 'Turn on check-ins, appointments, or both.');
                }
            },
        ];
    }
}
