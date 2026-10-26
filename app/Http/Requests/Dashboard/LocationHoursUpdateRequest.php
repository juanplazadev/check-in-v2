<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\Dashboard\WeeklyHours;
use App\Models\Location;
use App\Models\User;
use App\Queries\LocationDirectory;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Staff replacing a location's weekly check-in or appointment hours. Every
 * day is sent, keyed by Carbon's day number (0 = Sunday), so a day left out
 * cannot be mistaken for a closed one.
 */
#[FailOnUnknownFields]
final class LocationHoursUpdateRequest extends FormRequest
{
    /**
     * The end of a day open around the clock, as the time input sends it.
     */
    public const string END_OF_DAY = WeeklyHours::END_OF_DAY;

    /**
     * The location being edited, scoped like the location's page, so one the
     * user may not see is a 404.
     */
    public ?Location $location = null {
        get {
            $user = $this->user();

            return $this->location ??= $user instanceof User
                ? resolve(LocationDirectory::class)->find($user, (string) $this->route('uuid'))
                : null;
        }
    }

    /**
     * Who may edit hours is enforced by the route's permission middleware, and
     * which locations the user may see by the LocationDirectory lookup.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(WeeklyHours $weeklyHours): array
    {
        return $weeklyHours->rules('days');
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return resolve(WeeklyHours::class)->messages('days');
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(WeeklyHours $weeklyHours): array
    {
        return [
            function (Validator $validator) use ($weeklyHours): void {
                if ($validator->errors()->isEmpty()) {
                    $weeklyHours->checkCloseAfterOpen($validator, 'days');
                }
            },
        ];
    }

    /**
     * Each day's hours as the schedule tables store them, or null when closed.
     *
     * @return array<int, array{open_time: string, close_time: string}|null>
     */
    public function days(): array
    {
        return resolve(WeeklyHours::class)->toSchedule((array) $this->validated('days'));
    }

    /**
     * A location the user may not see is a 404 before any validation.
     */
    protected function prepareForValidation(): void
    {
        abort_unless($this->location instanceof Location, 404);
    }
}
