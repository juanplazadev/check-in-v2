<?php

declare(strict_types=1);

use App\Dashboard\BroadcastChannels;
use App\Enums\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{uuid}', fn (User $user, string $uuid): bool => $user->uuid === $uuid);

/*
 * The staff dashboard's live feeds, one channel per staff member: each event
 * is pushed only to its location's audience (App\Dashboard\BroadcastChannels),
 * so the gate here is just "your own channel, verified, and allowed to view
 * the live queue".
 */
Broadcast::channel(
    BroadcastChannels::CHECK_INS,
    fn (User $user, string $userUuid): bool => $user->uuid === $userUuid && $user->hasVerifiedEmail() && $user->can(Permission::ViewLiveQueue),
);

Broadcast::channel(
    BroadcastChannels::APPOINTMENTS,
    fn (User $user, string $userUuid): bool => $user->uuid === $userUuid && $user->hasVerifiedEmail() && $user->can(Permission::ViewLiveQueue),
);
