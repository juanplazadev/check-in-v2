<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserHistoryEvent;
use App\Models\User;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Redeems an invitation: checks the token on the invites broker, sets the
 * staff member's first password and logs the acceptance on their trail. The
 * broker deletes the token, so the link works once.
 */
final readonly class AcceptInvitationAction
{
    public function __construct(private RecordUserHistoryAction $recordUserHistory) {}

    /**
     * @throws ValidationException When the token is wrong, used or expired.
     * @throws Throwable
     */
    public function handle(string $email, string $token, string $password): User
    {
        $accepted = null;

        // A full closure, not an arrow function: $accepted must be shared by reference.
        $status = DB::transaction(function () use ($email, $token, $password, &$accepted): string {
            return Password::broker('invites')->reset(
                ['email' => $email, 'token' => $token, 'password' => $password],
                function (User $user, string $password) use (&$accepted): void {
                    $user->forceFill([
                        'password' => $password,
                        'remember_token' => Str::random(60),
                        'password_change_required_at' => null,
                    ])->save();

                    $this->recordUserHistory->handle($user, UserHistoryEvent::InvitationAccepted);

                    $accepted = $user;
                },
            );
        });

        if ($status !== PasswordBroker::PASSWORD_RESET || ! $accepted instanceof User) {
            throw ValidationException::withMessages([
                'email' => __('messages.choosePassword.invitation.invalid'),
            ]);
        }

        return $accepted;
    }
}
