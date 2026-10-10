<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UserIndexRequest extends FormRequest
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
            'type' => ['sometimes', 'nullable', Rule::in(['staff', 'driver'])],
            'roles' => ['sometimes', 'array', 'max:10'],
            'roles.*' => [Rule::enum(Role::class)],
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
        ];
    }

    /**
     * @return array{search: string|null, type: string|null, roles: list<string>}
     */
    public function filters(): array
    {
        $search = mb_trim((string) $this->validated('search'));

        return [
            'search' => $search === '' ? null : $search,
            'type' => $this->validated('type'),
            'roles' => array_values(array_map(strval(...), (array) $this->validated('roles', []))),
        ];
    }
}
