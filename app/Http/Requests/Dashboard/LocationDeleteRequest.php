<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\Models\Location;
use App\Models\User;
use App\Queries\LocationDirectory;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

/**
 * An admin soft-deleting a location. A reason is required, since the location
 * disappears for drivers, guests and staff at once.
 */
#[FailOnUnknownFields]
final class LocationDeleteRequest extends FormRequest
{
    /**
     * The location being deleted, scoped like the location's page, so one the
     * user may not see, or one already deleted, is a 404.
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
     * Who may delete is enforced by the route's permission middleware, and
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
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function reason(): string
    {
        return mb_trim((string) $this->validated('reason'));
    }

    /**
     * A location the user may not see is a 404 before any validation.
     */
    protected function prepareForValidation(): void
    {
        abort_unless($this->location instanceof Location, 404);
    }
}
