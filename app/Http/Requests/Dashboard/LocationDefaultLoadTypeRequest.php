<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\Models\LoadType;
use App\Models\Location;
use App\Models\User;
use App\Queries\LocationDirectory;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Staff choosing who picks the load type at a location: drivers when they
 * book (no load type sent), or staff, with the load type every booking gets.
 */
#[FailOnUnknownFields]
final class LocationDefaultLoadTypeRequest extends FormRequest
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
        return [
            'load_type' => [
                'nullable',
                'uuid',
                Rule::exists('load_types', 'uuid')
                    ->where('location_id', $this->location?->id)
                    ->where('is_active', true),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'load_type.exists' => 'Choose an active load type at this location.',
        ];
    }

    /**
     * The load type every booking gets, or null when drivers choose.
     */
    public function defaultLoadType(): ?LoadType
    {
        $uuid = $this->validated('load_type');

        if ($uuid === null || ! $this->location instanceof Location) {
            return null;
        }

        return LoadType::query()->whereBelongsTo($this->location)->where('uuid', (string) $uuid)->firstOrFail();
    }

    /**
     * A location the user may not see is a 404 before any validation.
     */
    protected function prepareForValidation(): void
    {
        abort_unless($this->location instanceof Location, 404);
    }
}
