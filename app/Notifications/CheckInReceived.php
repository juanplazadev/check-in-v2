<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\CheckIn;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * A new check-in, as it appears in a staff member's dashboard notification bell.
 *
 * Stored for the bell and broadcast on the user's private channel so an open
 * dashboard refreshes the bell without a page load. Not a
 * {@see \App\History\RecordNotification}: the record trail is for what the
 * driver was sent, not for internal staff alerts.
 */
final class CheckInReceived extends Notification implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * Stored in the `type` column, which the bell switches on. Mirrored in
     * NotificationsMenu.tsx.
     */
    public const string TYPE = 'check-in.created';

    public function __construct(public CheckIn $checkIn) {}

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
     * @return array{checkInUuid: string, referenceNumber: string, driverName: string, locationName: string}
     */
    public function toArray(User $notifiable): array
    {
        $this->checkIn->loadMissing('location');

        return [
            'checkInUuid' => $this->checkIn->uuid,
            'referenceNumber' => $this->checkIn->reference_number,
            'driverName' => $this->checkIn->drivers_name,
            'locationName' => $this->checkIn->location->name,
        ];
    }

    public function toBroadcast(User $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
