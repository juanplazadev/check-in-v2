<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Auth\RegistrationGate;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

final readonly class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function __construct(private RegistrationGate $registrationGate) {}

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        if (! $this->registrationGate->isAllowed()) {
            throw ValidationException::withMessages([
                'email' => __('messages.register.notAllowed'),
            ]);
        }

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
        ])->validate();

        $claimType = $this->registrationGate->claimType();
        $claimId = $this->registrationGate->claimId();

        // Stash the guest check-in / appointment as pending; it is attached to
        // this user only once the email address is verified.
        $user = User::query()->create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
            'cellphone' => $this->registrationGate->cellphone(),
            'pending_check_in_id' => $claimType === 'check_in' ? $claimId : null,
            'pending_appointment_id' => $claimType === 'appointment' ? $claimId : null,
        ]);

        $user->assignRole(Role::Driver);

        $this->registrationGate->consume();

        return $user;
    }
}
