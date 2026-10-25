<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\Models\Location;
use App\Models\User;
use App\Queries\LocationDirectory;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

/**
 * An admin turning a location on or off, or one of its features. Every flag
 * is optional, so each control sends only its own, but at least one is
 * required; any other field fails the whole request.
 */
#[FailOnUnknownFields]
final class LocationStatusUpdateRequest extends FormRequest
{
    /**
     * The status columns, as the payload names them.
     *
     * @var list<string>
     */
    public const array FIELDS = ['is_active', 'is_checkins_enabled', 'is_appointments_enabled'];

    /**
     * The location being changed, scoped like the location's page, so one the
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
     * Who may change it is enforced by the route's permission middleware, and
     * which locations the user may see by the LocationDirectory lookup.
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
        $rules = [];

        foreach (self::FIELDS as $field) {
            $others = implode(',', array_diff(self::FIELDS, [$field]));
            $rules[$field] = ['required_without_all:'.$others, 'boolean'];
        }

        return $rules;
    }

    /**
     * The validated flags, cast to booleans.
     *
     * @return array<string, bool>
     */
    public function flags(): array
    {
        return array_map(boolval(...), $this->validated());
    }

    /**
     * A location the user may not see is a 404 before any validation.
     */
    protected function prepareForValidation(): void
    {
        abort_unless($this->location instanceof Location, 404);
    }
}
