<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\Enums\Permission;
use App\Models\Appointment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;

/**
 * Staff dropping an appointment on the dock view. The time is the location's
 * local time, so the server settles daylight saving. Whether the position
 * fits is checked by MoveAppointmentAction, under the location lock.
 */
#[FailOnUnknownFields]
final class AppointmentMoveRequest extends FormRequest
{
    private ?Appointment $appointment = null;

    /**
     * Needs the validated time, so the check that depends on what changes is
     * in passedValidation(). The route requires at least one of the two
     * permissions.
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
            'scheduled_for' => ['required', 'date_format:Y-m-d H:i:s'],
            'dock' => ['present', 'nullable', 'uuid'],
        ];
    }

    /**
     * The booking, matching the detail page's lookup: one at a location the
     * user may not see is a 404, and so is an archived one.
     */
    public function appointment(): Appointment
    {
        /** @var User $user */
        $user = $this->user();

        return $this->appointment ??= Appointment::query()
            ->visibleTo($user)
            ->with(['location', 'dock'])
            ->where('uuid', (string) $this->route('uuid'))
            ->firstOrFail();
    }

    public function start(): CarbonInterface
    {
        return Date::parse((string) $this->validated('scheduled_for'), $this->appointment()->location->timezone);
    }

    public function dockUuid(): ?string
    {
        $dock = $this->validated('dock');

        return is_string($dock) ? $dock : null;
    }

    /**
     * @throws AuthorizationException When the user may not make this kind of change.
     */
    protected function passedValidation(): void
    {
        /** @var User $user */
        $user = $this->user();
        $appointment = $this->appointment();

        $movesTime = ! $this->start()->equalTo($appointment->scheduled_for);
        $movesDock = $this->dockUuid() !== $appointment->dock?->uuid;

        if (($movesTime && ! $user->can(Permission::RescheduleAppointments)) || ($movesDock && ! $user->can(Permission::AssignAppointmentDocks))) {
            throw new AuthorizationException;
        }
    }
}
