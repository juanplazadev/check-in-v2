<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\Dashboard\NewUserOptions;
use App\Enums\Role;
use App\Enums\UserAccessMethod;
use App\Models\Location;
use App\Models\User;
use App\Queries\LocationDirectory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

/**
 * The add user dialog. Staff roles only: drivers register themselves at
 * check-in. A role that does not see every location needs at least one
 * location, since a scoped user with none sees nothing.
 */
#[FailOnUnknownFields]
final class UserStoreRequest extends FormRequest
{
    /**
     * Resolved once by the after hook and reused by the controller.
     *
     * @var Collection<int, Location>|null
     */
    private ?Collection $resolvedLocations = null;

    /**
     * Who may add a user is enforced by the route's permission middleware.
     */
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'cellphone' => ['nullable', 'string', 'regex:/^\+1\d{10}$/'],
            'role' => ['required', Rule::in(array_map(fn (Role $role): string => $role->value, Role::staff()))],
            'locations' => ['present', 'array', 'max:100'],
            'locations.*' => ['required', 'uuid', 'distinct'],
            'access' => ['required', Rule::enum(UserAccessMethod::class)],
            'password' => ['nullable', 'string', 'required_if:access,'.UserAccessMethod::TemporaryPassword->value, Password::default()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Someone already has an account with this email.',
            'cellphone.regex' => 'Enter a 10-digit US cellphone number.',
            'password.required_if' => 'Set a temporary password, or send an invite instead.',
        ];
    }

    /**
     * The locations must be ones the admin can see. They are ignored for a
     * role that sees every location, so switching roles in the dialog never
     * leaves stray attachments behind.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(NewUserOptions $options, LocationDirectory $directory): array
    {
        return [
            function (Validator $validator) use ($options, $directory): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($options->seesAllLocations($this->role())) {
                    $this->resolvedLocations = new Collection;

                    return;
                }

                /** @var list<string> $uuids */
                $uuids = $validator->getData()['locations'];

                if ($uuids === []) {
                    $validator->errors()->add('locations', 'Pick at least one location, or this user will see nothing.');

                    return;
                }

                /** @var User $viewer */
                $viewer = $this->user();

                $this->resolvedLocations = $directory->execute($viewer)->whereIn('uuid', $uuids)->values();

                if ($this->resolvedLocations->count() !== count($uuids)) {
                    $validator->errors()->add('locations', 'One of these locations is no longer available.');
                }
            },
        ];
    }

    public function role(): Role
    {
        return Role::from($this->string('role')->toString());
    }

    public function accessMethod(): UserAccessMethod
    {
        return UserAccessMethod::from($this->string('access')->toString());
    }

    /**
     * @return Collection<int, Location>
     */
    public function locations(): Collection
    {
        return $this->resolvedLocations ?? new Collection;
    }

    /**
     * Matches the profile form: the number is stored as "+1XXXXXXXXXX", and a
     * typed leading country code is not doubled.
     */
    protected function prepareForValidation(): void
    {
        $digits = (string) preg_replace('/\D/', '', $this->string('cellphone')->toString());

        if (mb_strlen($digits) === 11 && str_starts_with($digits, '1')) {
            $digits = mb_substr($digits, 1);
        }

        $this->merge([
            'email' => mb_strtolower(mb_trim($this->string('email')->toString())),
            'cellphone' => $digits === '' ? null : '+1'.$digits,
        ]);
    }
}
