<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

final class LocationUsersRequest extends FormRequest
{
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
            // See SearchRequest: no control characters or invalid UTF-8.
            'search' => ['sometimes', 'nullable', 'string', 'max:100', 'regex:/^[^\p{C}]+$/u'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
        ];
    }

    public function search(): ?string
    {
        $search = mb_trim((string) $this->validated('search'));

        return $search === '' ? null : $search;
    }
}
