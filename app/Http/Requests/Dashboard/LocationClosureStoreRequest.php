<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\Enums\ScheduleFeature;
use App\Models\Location;
use App\Models\User;
use App\Queries\LocationDirectory;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Staff closing a location for a day, or keeping special hours, for
 * check-ins, appointments or both. The date is today or later in the
 * location's timezone, since past days are history.
 */
#[FailOnUnknownFields]
final class LocationClosureStoreRequest extends FormRequest
{
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
     * Who may add closures is enforced by the route's permission middleware,
     * and which locations the user may see by the LocationDirectory lookup.
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
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$this->localToday()],
            'reason' => ['required', 'string', 'max:255'],
            'is_closed' => ['required', 'boolean'],
            'open_time' => ['nullable', 'exclude_if:is_closed,true', 'required_if:is_closed,false', 'date_format:H:i'],
            'close_time' => ['nullable', 'exclude_if:is_closed,true', 'required_if:is_closed,false', 'date_format:H:i'],
            'applies_to' => ['required', 'array', 'min:1'],
            'applies_to.*' => ['required', 'distinct', Rule::in(array_map(fn (ScheduleFeature $feature): string => $feature->closureKey(), ScheduleFeature::cases()))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.required' => 'Choose a date.',
            'date.after_or_equal' => 'Choose today or a later date.',
            'reason.required' => 'Enter a reason.',
            'open_time.required_if' => 'Choose an opening time.',
            'close_time.required_if' => 'Choose a closing time.',
            'open_time.date_format' => 'Enter a time like 08:00.',
            'close_time.date_format' => 'Enter a time like 17:00.',
            'applies_to.required' => 'Choose check-ins, appointments or both.',
            'applies_to.min' => 'Choose check-ins, appointments or both.',
        ];
    }

    /**
     * Special hours must close after they open. A date that already has a
     * closure is caught by AddLocationClosureAction, under the location lock.
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

                $data = $validator->getData();

                if (! filter_var($data['is_closed'], FILTER_VALIDATE_BOOLEAN) && $data['close_time'] <= $data['open_time']) {
                    $validator->errors()->add('close_time', 'Must be after the opening time.');
                }
            },
        ];
    }

    /**
     * @return list<ScheduleFeature>
     */
    public function features(): array
    {
        return array_values(array_map(
            ScheduleFeature::fromClosureKey(...),
            array_map(strval(...), (array) $this->validated('applies_to')),
        ));
    }

    /**
     * The override row as both override tables store it.
     *
     * @return array{override_date: string, is_closed: bool, open_time: string|null, close_time: string|null, reason: string}
     */
    public function closure(): array
    {
        $isClosed = filter_var($this->validated('is_closed'), FILTER_VALIDATE_BOOLEAN);
        $closeTime = (string) $this->validated('close_time');

        return [
            'override_date' => (string) $this->validated('date'),
            'is_closed' => $isClosed,
            'open_time' => $isClosed ? null : $this->validated('open_time').':00',
            'close_time' => $isClosed ? null : ($closeTime === LocationHoursUpdateRequest::END_OF_DAY ? '23:59:59' : $closeTime.':00'),
            'reason' => (string) $this->validated('reason'),
        ];
    }

    /**
     * A location the user may not see is a 404 before any validation.
     */
    protected function prepareForValidation(): void
    {
        abort_unless($this->location instanceof Location, 404);
    }

    private function localToday(): string
    {
        $location = $this->location;

        // prepareForValidation has already 404ed a missing one; this narrows the type.
        abort_unless($location instanceof Location, 404);

        return now()->setTimezone($location->timezone)->toDateString();
    }
}
