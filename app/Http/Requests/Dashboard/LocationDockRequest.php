<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\Models\Building;
use App\Models\Dock;
use App\Models\Location;
use App\Models\User;
use App\Queries\LocationDirectory;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Staff adding, editing or removing a dock on a location's docks page. A
 * removal sends no fields. Whether a dock can be turned off or removed while
 * appointments use it is decided by the action, under the location lock.
 */
#[FailOnUnknownFields]
final class LocationDockRequest extends FormRequest
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
     * The dock named in the URL, on this location only; null when adding one.
     */
    public ?Dock $dock = null {
        get {
            $uuid = $this->route('dockUuid');

            if ($uuid === null || ! $this->location instanceof Location) {
                return null;
            }

            return $this->dock ??= Dock::query()
                ->whereBelongsTo($this->location)
                ->where('uuid', (string) $uuid)
                ->firstOrFail();
        }
    }

    /**
     * Who may manage docks is enforced by the route's permission middleware,
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
        if ($this->isMethod('DELETE')) {
            return [];
        }

        return [
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('docks', 'name')
                    ->where('location_id', $this->location?->id)
                    ->ignore($this->dock?->id),
            ],
            'building' => [
                'nullable',
                'uuid',
                Rule::exists('buildings', 'uuid')->where('location_id', $this->location?->id),
            ],
            'is_active' => ['required', 'boolean'],
            'accepts_appointments' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Enter a name for the dock, like Door 12.',
            'name.unique' => 'This location already has a dock with that name.',
            'building.exists' => 'Choose a building at this location.',
        ];
    }

    /**
     * The dock row as the docks table stores it.
     *
     * @return array{name: string, building_id: int|null, is_active: bool, accepts_appointments: bool}
     */
    public function dockAttributes(): array
    {
        $buildingUuid = $this->validated('building');

        return [
            'name' => (string) $this->validated('name'),
            'building_id' => $buildingUuid === null
                ? null
                : Building::query()->where('uuid', (string) $buildingUuid)->value('id'),
            'is_active' => filter_var($this->validated('is_active'), FILTER_VALIDATE_BOOLEAN),
            'accepts_appointments' => filter_var($this->validated('accepts_appointments'), FILTER_VALIDATE_BOOLEAN),
        ];
    }

    /**
     * A location or dock the user may not see is a 404 before any validation.
     */
    protected function prepareForValidation(): void
    {
        abort_unless($this->location instanceof Location, 404);

        // Reading the property 404s a dock that is not on this location.
        abort_if($this->route('dockUuid') !== null && ! $this->dock instanceof Dock, 404);
    }
}
