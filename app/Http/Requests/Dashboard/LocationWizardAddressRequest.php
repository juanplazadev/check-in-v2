<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\Models\Address;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The add location wizard's address step. US only, since the Census geocoder
 * that places the address on the map only knows US addresses.
 */
#[FailOnUnknownFields]
final class LocationWizardAddressRequest extends FormRequest
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
            'street1' => ['required', 'string', 'max:255'],
            'street2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'size:2', 'alpha'],
            'zip_code' => ['required', 'string', 'regex:/^\d{5}$/'],
            'zip_code_4' => ['required', 'string', 'regex:/^\d{4}$/'],
            'country' => ['required', 'string', Rule::in(['US'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'zip_code.regex' => 'The ZIP code must be 5 digits.',
            'zip_code_4.regex' => 'The ZIP+4 must be 4 digits.',
            'country.in' => 'Only US addresses are supported.',
        ];
    }

    /**
     * Street, city, state and ZIP are unique across addresses, so an address
     * another location already has is a field error rather than a 500.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $taken = Address::query()
                    ->where('street1', $this->input('street1'))
                    ->where('city', $this->input('city'))
                    ->where('state', $this->input('state'))
                    ->where('zip_code', $this->input('zip_code'))
                    ->exists();

                if ($taken) {
                    $validator->errors()->add('street1', 'Another location already has this address.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['state', 'country'] as $key) {
            if ($this->has($key)) {
                $normalized[$key] = Str::upper((string) $this->input($key));
            }
        }

        $this->merge($normalized);
    }
}
