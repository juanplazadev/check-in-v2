<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\Enums\AppointmentPauseDuration;
use App\Models\Location;
use App\Models\User;
use App\Queries\LocationDirectory;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Staff pausing new bookings at a location. The reason is shown to guests.
 */
#[FailOnUnknownFields]
final class LocationAppointmentPauseRequest extends FormRequest
{
    /**
     * The location being paused, scoped like the location's page, so one the
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
     * Who may pause is enforced by the route's permission middleware, and
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
            'reason' => ['required', 'string', 'max:255'],
            'duration' => ['required', Rule::enum(AppointmentPauseDuration::class)],
        ];
    }

    public function duration(): AppointmentPauseDuration
    {
        return AppointmentPauseDuration::from((string) $this->validated('duration'));
    }

    /**
     * A location the user may not see is a 404 before any validation.
     */
    protected function prepareForValidation(): void
    {
        abort_unless($this->location instanceof Location, 404);
    }
}
