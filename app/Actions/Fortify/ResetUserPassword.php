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
use Laravel\Fortify\Contracts\ResetsUserPasswords;
use Throwable;

final readonly class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    public function __construct(private RecordUserHistoryAction $history) {}

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     * @throws Throwable
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $password = Hash::make($input['password']);

        DB::transaction(function () use ($user, $password): void {
            $user->forceFill(['password' => $password, 'password_change_required_at' => null])->save();

            $this->history->handle($user, UserHistoryEvent::PasswordReset);
        });
    }
}
