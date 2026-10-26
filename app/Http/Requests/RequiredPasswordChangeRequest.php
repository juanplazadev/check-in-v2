<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\Fortify\PasswordValidationRules;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Validator;

/**
 * Replacing the temporary password an admin set. The current password is not
 * asked for again: the user signed in with it moments ago. Reusing it is
 * refused, since the admin knows it.
 */
final class RequiredPasswordChangeRequest extends FormRequest
{
    use PasswordValidationRules;

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
            'password' => $this->passwordRules(),
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                /** @var User $user */
                $user = $this->user();

                if (Hash::check($this->string('password')->toString(), $user->password)) {
                    $validator->errors()->add('password', __('messages.choosePassword.required.samePassword'));
                }
            },
        ];
    }
}
