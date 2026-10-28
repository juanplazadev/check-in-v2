<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Staff sending a waiting truck to a door, and whether to text the driver.
 * Whether the door is at the check-in's location, active and free is checked
 * by AssignCheckInDockAction, under the location lock.
 */
#[FailOnUnknownFields]
final class CheckInDockRequest extends FormRequest
{
    /**
     * Who may assign doors is enforced by the route's permission middleware,
     * and which check-ins the user may see by the controller's visibleTo lookup.
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
            'notify' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'dock.required' => 'Choose a door.',
        ];
    }

    public function dockUuid(): string
    {
        return (string) $this->validated('dock');
    }

    public function shouldNotify(): bool
    {
        return filter_var($this->validated('notify'), FILTER_VALIDATE_BOOLEAN);
    }
}
