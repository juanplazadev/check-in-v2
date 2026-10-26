<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\Dashboard\LocationTimezones;
use App\Models\Address;
use App\Models\Location;
use App\Models\User;
use App\Queries\LocationDirectory;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * An admin edit of one section of a location's details. Every field is
 * optional, so each section sends only its own; any field outside these rules
 * fails the whole request, so a tampered payload cannot reach a column the
 * edit does not cover, such as the coordinates or the feature toggles.
 */
#[FailOnUnknownFields]
final class LocationDetailsUpdateRequest extends FormRequest
{
    /**
     * Address columns, as the payload names them. The rest are location columns.
     *
     * @var list<string>
     */
    public const array ADDRESS_FIELDS = ['street1', 'street2', 'city', 'state', 'zip_code', 'zip_code_4', 'country'];

    /**
     * The location being edited, scoped like the location's page, so one the
     * user may not see is a 404.
     */
    public ?Location $location = null {
        get {
            $user = $this->user();

            return $this->location ??= $user instanceof User
                ? resolve(LocationDirectory::class)->find($user, (string) $this->route('uuid'))
                : null;
        }
    }

    /**
     * Who may edit is enforced by the route's permission middleware, and
     * which locations the user may see by the LocationDirectory lookup.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(LocationTimezones $timezones): array
    {
        $location = $this->location;

        return [
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:100'],
            'abbreviation' => ['sometimes', 'required', 'string', 'alpha_num', 'min:2', 'max:4'],
            'timezone' => ['sometimes', 'required', 'string', Rule::in($location instanceof Location ? $timezones->for($location) : [])],
            'phone' => ['sometimes', 'required', 'string', 'max:20', 'regex:/^[0-9 ()+.-]+$/'],
            'phone_ext' => ['sometimes', 'nullable', 'string', 'max:10', 'regex:/^\d+$/'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255'],
            'max_distance_allowed' => ['sometimes', 'required', 'integer', 'min:1', 'max:500'],
            'street1' => ['sometimes', 'required', 'string', 'max:255'],
            'street2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'required', 'string', 'max:100'],
            'state' => ['sometimes', 'required', 'string', 'size:2', 'alpha'],
            'zip_code' => ['sometimes', 'required', 'string', 'regex:/^\d{5}$/'],
            'zip_code_4' => ['sometimes', 'required', 'string', 'regex:/^\d{4}$/'],
            'country' => ['sometimes', 'required', 'string', 'size:2', 'alpha'],
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
            'zip_code.regex' => 'The ZIP code must be 5 digits.',
            'zip_code_4.regex' => 'The ZIP+4 must be 4 digits.',
            'timezone.in' => 'Choose a timezone from the list.',
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
                $address = $this->location?->address;

                if ($validator->errors()->isNotEmpty() || ! $address instanceof Address || ! $this->hasAny(['street1', 'city', 'state', 'zip_code'])) {
                    return;
                }

                $taken = Address::query()
                    ->whereKeyNot($address->id)
                    ->where('street1', $this->input('street1', $address->street1))
                    ->where('city', $this->input('city', $address->city))
                    ->where('state', $this->input('state', $address->state))
                    ->where('zip_code', $this->input('zip_code', $address->zip_code))
                    ->exists();

                if ($taken) {
                    $validator->errors()->add('street1', 'Another location already has this address.');
                }
            },
        ];
    }

    /**
     * A location the user may not see is a 404 before any validation. Only
     * keys the client sent are normalized, so nothing is merged in that the
     * unknown field check would then have to allow.
     */
    protected function prepareForValidation(): void
    {
        abort_unless($this->location instanceof Location, 404);

        $normalized = [];

        foreach (['abbreviation', 'state', 'country'] as $key) {
            if ($this->has($key)) {
                $normalized[$key] = Str::upper((string) $this->input($key));
            }
        }

        $this->merge($normalized);
    }
}
