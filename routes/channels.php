<?php

declare(strict_types=1);

use App\Dashboard\BroadcastChannels;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
 * The staff dashboard's live feeds, one channel per staff member: each event
 * is pushed only to its location's audience (App\Dashboard\BroadcastChannels),
 * so the gate here is just "your own channel, verified, with a staff role".
 */
Broadcast::channel(
    BroadcastChannels::CHECK_INS,
    fn (User $user, string $userId): bool => $user->id === (int) $userId && $user->hasVerifiedEmail() && $user->hasAnyRole(Role::staff()),
);

Broadcast::channel(
    BroadcastChannels::APPOINTMENTS,
    fn (User $user, string $userId): bool => $user->id === (int) $userId && $user->hasVerifiedEmail() && $user->hasAnyRole(Role::staff()),
);
