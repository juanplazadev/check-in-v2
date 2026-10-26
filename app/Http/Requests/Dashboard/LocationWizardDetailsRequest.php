<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The add location wizard's first step: what the location is called and how
 * to reach it. The abbreviation is not asked for: one is made up from the
 * name when the location is created, and can be changed on its Details page.
 */
#[FailOnUnknownFields]
final class LocationWizardDetailsRequest extends FormRequest
{
    /**
     * Who may add a location is enforced by the route's permission middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Mirrors LocationDetailsUpdateRequest, which edits the same fields later.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^[0-9 ()+.-]+$/'],
            'phone_ext' => ['nullable', 'string', 'max:10', 'regex:/^\d+$/'],
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'The phone may only contain digits, spaces and ( ) + . -',
            'phone_ext.regex' => 'The extension may only contain digits.',
        ];
    }
}
