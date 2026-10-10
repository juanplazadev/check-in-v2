<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\Enums\CheckInStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CheckInIndexRequest extends FormRequest
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
            'status' => ['sometimes', 'nullable', Rule::enum(CheckInStatus::class)],
            'locations' => ['sometimes', 'array', 'max:50'],
            'locations.*' => ['integer'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
        ];
    }

    /**
     * @return array{search: string|null, status: string|null, locations: list<int>}
     */
    public function filters(): array
    {
        $search = mb_trim((string) $this->validated('search'));

        return [
            'search' => $search === '' ? null : $search,
            'status' => $this->validated('status'),
            'locations' => array_values(array_map(intval(...), (array) $this->validated('locations', []))),
        ];
    }
}
