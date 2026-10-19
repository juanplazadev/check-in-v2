<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

final class AppointmentNoteRequest extends FormRequest
{
    /**
     * Who may write notes is enforced by the route's permission middleware,
     * and which appointments the user may see by the controller's visibleTo
     * lookup.
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
            'body' => ['required', 'string', 'max:2000'],
        ];
    }

    public function body(): string
    {
        return (string) $this->validated('body');
    }
}
