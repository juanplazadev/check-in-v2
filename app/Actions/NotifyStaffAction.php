<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Notification;

final readonly class NotifyStaffAction
{
    /**
     * Drop a notification into every staff member's dashboard bell.
     *
     * Same audience as the live check-in channel: verified users holding any
     * staff role. Splits per location once staff are tied to locations.
     */
    public function handle(BaseNotification $notification): void
    {
        $staff = User::role(Role::staff())
            ->whereNotNull('email_verified_at')
            ->get();

        Notification::send($staff, $notification);
    }
}
