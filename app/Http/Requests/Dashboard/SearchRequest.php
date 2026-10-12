<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

final class SearchRequest extends FormRequest
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
            // No control characters (null bytes included). preg_match also fails on
            // invalid UTF-8, so that is turned away here too instead of reaching
            // Postgres and coming back as a 500.
            'q' => ['required', 'string', 'min:2', 'max:100', 'regex:/^[^\p{C}]+$/u'],
        ];
    }

    public function term(): string
    {
        return mb_trim((string) $this->validated('q'));
    }

    /**
     * Trim before validating, so "  a " counts as one character, not four.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('q'))) {
            $this->merge(['q' => mb_trim($this->input('q'))]);
        }
    }
}
