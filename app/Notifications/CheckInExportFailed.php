<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * A check-in export that could not be built, so the staff member who asked
 * for it is not left waiting on a file that never comes.
 */
final class CheckInExportFailed extends Notification
{
    /**
     * Stored in the `type` column, which the bell switches on. Mirrored in
     * NotificationsMenu.tsx.
     */
    public const string TYPE = 'check-in-export.failed';

    public function __construct(public string $filename) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function databaseType(User $notifiable): string
    {
        return self::TYPE;
    }

    /**
     * @return array{filename: string}
     */
    public function toArray(User $notifiable): array
    {
        return ['filename' => $this->filename];
    }

    public function toBroadcast(User $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
