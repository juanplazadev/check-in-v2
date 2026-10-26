<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Support\Facades\Password;

/**
 * Emails a staff member the link to choose their first password. The token
 * lives on the invites broker, whose week-long expiry is its own; a new
 * invitation replaces any earlier one. Queued after commit, so the worker
 * never looks for a user whose creation rolled back.
 */
final readonly class SendUserInvitationAction
{
    public function handle(User $user, User $inviter): void
    {
        $token = Password::broker('invites')->createToken($user);

        $user->notify(new UserInvitation($token, $inviter->name)->locale(app()->getLocale())->afterCommit());
    }
}
