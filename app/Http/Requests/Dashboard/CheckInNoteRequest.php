<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

final class CheckInNoteRequest extends FormRequest
{
    /**
     * Staff-only is enforced by the route's role middleware, and which
     * check-ins the user may see by the controller's visibleTo lookup.
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
