<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\Dashboard\WeeklyHours;
use App\Enums\LocationWizardStep;
use App\Session\LocationWizardSession;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The add location wizard's hours step: a week of hours for each feature
 * turned on in the features step, and none for a feature turned off.
 */
#[FailOnUnknownFields]
final class LocationWizardHoursRequest extends FormRequest
{
    /**
     * The payload key of each feature's week, by its features step answer.
     *
     * @var array<string, string>
     */
    public const array FEATURE_KEYS = [
        'checkins' => 'is_checkins_enabled',
        'appointments' => 'is_appointments_enabled',
    ];

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
    public function rules(WeeklyHours $weeklyHours, LocationWizardSession $session): array
    {
        $features = $session->get(LocationWizardStep::Features) ?? [];
        $rules = [];

        foreach (self::FEATURE_KEYS as $key => $flag) {
            $rules = ($features[$flag] ?? false)
                ? [...$rules, ...$weeklyHours->rules($key)]
                : [...$rules, $key => ['prohibited']];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $weeklyHours = resolve(WeeklyHours::class);

        return [
            ...$weeklyHours->messages('checkins'),
            ...$weeklyHours->messages('appointments'),
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(WeeklyHours $weeklyHours): array
    {
        return [
            function (Validator $validator) use ($weeklyHours): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                foreach (array_keys(self::FEATURE_KEYS) as $key) {
                    $weeklyHours->checkCloseAfterOpen($validator, $key);
                }
            },
        ];
    }
}
