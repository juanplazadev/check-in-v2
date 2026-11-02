<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Staff changing an appointment's load type. Whether the load type is
 * active, at the appointment's location, and fits the dock and closing time
 * is checked by ChangeAppointmentLoadTypeAction, under the location lock.
 */
#[FailOnUnknownFields]
final class AppointmentLoadTypeRequest extends FormRequest
{
    /**
     * Who may change load types is enforced by the route's permission middleware,
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
            'load_type' => ['required', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'load_type.required' => 'Choose a load type.',
        ];
    }

    public function loadTypeUuid(): string
    {
        return (string) $this->validated('load_type');
    }
}
