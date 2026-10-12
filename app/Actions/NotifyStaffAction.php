<?php

declare(strict_types=1);

namespace App\Actions;

use App\Queries\DashboardAudience;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Notification;

final readonly class NotifyStaffAction
{
    public function __construct(private DashboardAudience $audience) {}

    /**
     * Drop a notification into the dashboard bell of every staff member who
     * can see records at the location. Same audience as the live feeds.
     */
    public function handle(BaseNotification $notification, int $locationId): void
    {
        Notification::send($this->audience->execute($locationId), $notification);
    }
}
