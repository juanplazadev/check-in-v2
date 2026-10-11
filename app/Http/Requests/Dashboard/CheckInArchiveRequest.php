<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

final class CheckInArchiveRequest extends FormRequest
{
    /**
     * Who may archive is enforced by the route's permission middleware, and
     * which check-ins the user may see by the controller's visibleTo lookup.
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
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * The reason, or null when none was given.
     */
    public function reason(): ?string
    {
        $reason = $this->validated('reason');

        return is_string($reason) && mb_trim($reason) !== '' ? $reason : null;
    }
}
