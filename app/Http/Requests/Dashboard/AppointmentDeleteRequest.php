<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

final class AppointmentDeleteRequest extends FormRequest
{
    /**
     * Who may delete is enforced by the route's permission middleware, and
     * which appointments the user may see by the controller's visibleTo lookup.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A reason is required: the appointment's own trail is deleted with it, so
     * the reason in the staff member's trail is all that explains the gap.
     *
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
}
