<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

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
     * Stored as 23:59:59, the last moment the schedule parser counts as open.
     */
    public const string END_OF_DAY = '23:59';

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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'days' => ['required', 'array:0,1,2,3,4,5,6', 'size:7'],
            'days.*' => ['required', 'array:is_open,open_time,close_time'],
            'days.*.is_open' => ['required', 'boolean'],
            'days.*.open_time' => ['nullable', 'required_if_accepted:days.*.is_open', 'date_format:H:i'],
            'days.*.close_time' => ['nullable', 'required_if_accepted:days.*.is_open', 'date_format:H:i'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'days.*.open_time.required_if_accepted' => 'Choose an opening time.',
            'days.*.close_time.required_if_accepted' => 'Choose a closing time.',
            'days.*.open_time.date_format' => 'Enter a time like 08:00.',
            'days.*.close_time.date_format' => 'Enter a time like 17:00.',
        ];
    }

    /**
     * An open day must close after it opens; hours past midnight are not
     * supported by the schedule tables.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                foreach ($validator->getData()['days'] as $day => $hours) {
                    if (filter_var($hours['is_open'], FILTER_VALIDATE_BOOLEAN) && $hours['close_time'] <= $hours['open_time']) {
                        $validator->errors()->add("days.{$day}.close_time", 'Must be after the opening time.');
                    }
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
        $days = [];

        foreach ((array) $this->validated('days') as $day => $hours) {
            $days[(int) $day] = filter_var($hours['is_open'], FILTER_VALIDATE_BOOLEAN)
                ? [
                    'open_time' => $hours['open_time'].':00',
                    'close_time' => $hours['close_time'] === self::END_OF_DAY ? '23:59:59' : $hours['close_time'].':00',
                ]
                : null;
        }

        ksort($days);

        return $days;
    }

    /**
     * A location the user may not see is a 404 before any validation.
     */
    protected function prepareForValidation(): void
    {
        abort_unless($this->location instanceof Location, 404);
    }
}
