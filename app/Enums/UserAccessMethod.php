<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a staff account an admin creates gets its first password: the user
 * chooses one from an emailed invitation, or the admin sets a temporary one
 * the user must replace at first sign-in.
 */
enum UserAccessMethod: string
{
    case Invite = 'invite';
    case TemporaryPassword = 'temporary-password';
}
