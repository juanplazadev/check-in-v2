<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\Enums\AppointmentBookingSetting;
use App\Enums\LoadDirection;
use App\Models\LoadType;
use App\Models\Location;
use App\Models\User;
use App\Queries\LocationDirectory;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Staff adding, editing or removing a load type on a location's load types
 * page. A removal sends no fields. Whether a load type can be removed is
 * decided by the action, under the location lock.
 */
#[FailOnUnknownFields]
final class LocationLoadTypeRequest extends FormRequest
{
    /**
     * The longest a load type can hold a dock: a full shift.
     */
    public const int MAX_DURATION_MINUTES = 480;

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
     * The load type named in the URL, on this location only; null when adding one.
     */
    public ?LoadType $loadType = null {
        get {
            $uuid = $this->route('loadTypeUuid');

            if ($uuid === null || ! $this->location instanceof Location) {
                return null;
            }

            return $this->loadType ??= LoadType::query()
                ->whereBelongsTo($this->location)
                ->where('uuid', (string) $uuid)
                ->firstOrFail();
        }
    }

    /**
     * Who may manage load types is enforced by the route's permission
     * middleware, and which locations the user may see by the LocationDirectory lookup.
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
        if ($this->isMethod('DELETE')) {
            return [];
        }

        return [
            'name' => [
                'required',
                'string',
                'max:60',
                Rule::unique('load_types', 'name')
                    ->where('location_id', $this->location?->id)
                    ->ignore($this->loadType?->id),
            ],
            'direction' => ['required', Rule::enum(LoadDirection::class)],
            'duration_minutes' => [
                'required',
                'integer',
                'min:'.AppointmentBookingSetting::MINUTE_STEP,
                'max:'.self::MAX_DURATION_MINUTES,
                'multiple_of:'.AppointmentBookingSetting::MINUTE_STEP,
            ],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Enter a name for the load type, like Live unload.',
            'name.unique' => 'This location already has a load type with that name.',
            'duration_minutes.min' => sprintf('A load takes at least %d minutes.', AppointmentBookingSetting::MINUTE_STEP),
            'duration_minutes.max' => sprintf('A load can take up to %d hours.', self::MAX_DURATION_MINUTES / 60),
            'duration_minutes.multiple_of' => sprintf('Use steps of %d minutes.', AppointmentBookingSetting::MINUTE_STEP),
        ];
    }

    /**
     * The load type row as the load_types table stores it.
     *
     * @return array{name: string, direction: LoadDirection, duration_minutes: int, is_active: bool}
     */
    public function loadTypeAttributes(): array
    {
        return [
            'name' => (string) $this->validated('name'),
            'direction' => LoadDirection::from((string) $this->validated('direction')),
            'duration_minutes' => (int) $this->validated('duration_minutes'),
            'is_active' => filter_var($this->validated('is_active'), FILTER_VALIDATE_BOOLEAN),
        ];
    }

    /**
     * A location or load type the user may not see is a 404 before any validation.
     */
    protected function prepareForValidation(): void
    {
        abort_unless($this->location instanceof Location, 404);

        // Reading the property 404s a load type that is not on this location.
        abort_if($this->route('loadTypeUuid') !== null && ! $this->loadType instanceof LoadType, 404);
    }
}
