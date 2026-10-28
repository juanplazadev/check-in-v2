<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\Models\Building;
use App\Models\Location;
use App\Models\User;
use App\Queries\LocationDirectory;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Staff adding, renaming or removing a building on a location's docks page.
 * A removal sends no fields.
 */
#[FailOnUnknownFields]
final class LocationBuildingRequest extends FormRequest
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
     * The building named in the URL, on this location only; null when adding one.
     */
    public ?Building $building = null {
        get {
            $uuid = $this->route('buildingUuid');

            if ($uuid === null || ! $this->location instanceof Location) {
                return null;
            }

            return $this->building ??= Building::query()
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
                Rule::unique('buildings', 'name')
                    ->where('location_id', $this->location?->id)
                    ->ignore($this->building?->id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Enter a name for the building.',
            'name.unique' => 'This location already has a building with that name.',
        ];
    }

    /**
     * A location or building the user may not see is a 404 before any validation.
     */
    protected function prepareForValidation(): void
    {
        abort_unless($this->location instanceof Location, 404);

        // Reading the property 404s a building that is not on this location.
        abort_if($this->route('buildingUuid') !== null && ! $this->building instanceof Building, 404);
    }
}
