<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Actions\RecordUserHistoryAction;
use App\Enums\UserHistoryEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;
use Throwable;

final readonly class UpdateUserPassword implements UpdatesUserPasswords
{
    use PasswordValidationRules;

    public function __construct(private RecordUserHistoryAction $history) {}

    /**
     * Validate and update the user's password.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     * @throws Throwable
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => $this->passwordRules(),
        ], [
            'current_password.current_password' => __('messages.validation.currentPassword'),
        ])->validateWithBag('updatePassword');

        $password = Hash::make($input['password']);

        DB::transaction(function () use ($user, $password): void {
            $user->forceFill(['password' => $password, 'password_change_required_at' => null])->save();

            $this->history->handle($user, UserHistoryEvent::PasswordUpdated);
        });
    }
}
