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

/**
 * Staff removing a closure from the features listed, so a closure that
 * applies to both can be removed from one alone. Only today or later can be
 * removed; past closures are history.
 */
#[FailOnUnknownFields]
final class LocationClosureDestroyRequest extends FormRequest
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
     * Who may remove closures is enforced by the route's permission
     * middleware, and which locations the user may see by the
     * LocationDirectory lookup.
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
            'date.after_or_equal' => 'Past closures cannot be removed.',
        ];
    }

    /**
     * The date comes from the route, so it is validated with the body.
     *
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        return [...$this->all(), 'date' => $this->route('date')];
    }

    public function closureDate(): string
    {
        return (string) $this->validated('date');
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
