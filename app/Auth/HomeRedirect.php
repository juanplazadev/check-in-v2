<?php

declare(strict_types=1);

namespace App\Auth;

use App\Enums\Role;
use App\Models\User;

/**
 * Resolves where a user lands after authenticating, and where the guest
 * middleware sends an already signed-in user. Centralized so login,
 * registration and the guest pages stay in sync.
 *
 * Staff land on the dashboard; drivers, and anyone without a staff role,
 * land on their driver account. A missing user also gets the account route,
 * whose auth middleware bounces it to login.
 */
final class HomeRedirect
{
    public static function for(?User $user): string
    {
        return $user?->hasAnyRole(Role::staff())
            ? route('dashboard')
            : route('account');
    }
}
