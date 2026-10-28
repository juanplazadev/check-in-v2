<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Staff placing an appointment on a dock, or moving it. Whether the dock is
 * at the appointment's location, open and free is checked by
 * AssignAppointmentDockAction, under the location lock.
 */
#[FailOnUnknownFields]
final class AppointmentDockRequest extends FormRequest
{
    /**
     * Who may assign docks is enforced by the route's permission middleware,
     * and which appointments the user may see by the controller's visibleTo lookup.
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
            'dock' => ['required', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'dock.required' => 'Choose a dock.',
        ];
    }

    public function dockUuid(): string
    {
        return (string) $this->validated('dock');
    }
}
