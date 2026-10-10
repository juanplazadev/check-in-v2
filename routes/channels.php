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
 * Live check-in feed for the staff dashboard. Every staff role sees every
 * location until staff are tied to locations; then this splits per location.
 * Same gate as the dashboard routes: verified, with a staff role.
 */
Broadcast::channel(
    BroadcastChannels::CHECK_INS,
    fn (User $user): bool => $user->hasVerifiedEmail() && $user->hasAnyRole(Role::staff()),
);

/*
 * Live appointment feed for the live queue's arriving rail. Same audience as
 * the check-in feed.
 */
Broadcast::channel(
    BroadcastChannels::APPOINTMENTS,
    fn (User $user): bool => $user->hasVerifiedEmail() && $user->hasAnyRole(Role::staff()),
);
