<?php

declare(strict_types=1);

namespace App\Notifications;

use App\History\RecordNotification;
use App\Models\Building;
use App\Models\CheckIn;
use App\Models\Dock;
use App\Notifications\Channels\SmsChannel;
use App\Sms\SmsMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * Tells a checked-in driver which door to pull up to.
 *
 * Operational, not a copy of their record: it goes to the cellphone on the
 * check-in whether or not the driver has an account, and account
 * preferences do not apply. See {@see \App\Actions\SendDoorAssignmentAction}.
 *
 * The dock is captured when the text is queued, so a later move does not
 * change a text already on its way; the move sends its own.
 */
final class DoorAssignment extends Notification implements RecordNotification, ShouldQueue
{
    use SerializesModels;

    public function __construct(public CheckIn $checkIn, public Dock $dock) {}

    public function historyRecord(): CheckIn
    {
        return $this->checkIn;
    }

    public function historySubject(): string
    {
        return 'door-assignment';
    }

    /**
     * @return list<string>
     */
    public function via(AnonymousNotifiable $notifiable): array
    {
        return [SmsChannel::class];
    }

    public function toSms(AnonymousNotifiable $notifiable): SmsMessage
    {
        $building = $this->dock->building;

        return new SmsMessage(
            to: (string) $notifiable->routeNotificationFor('sms'),
            body: __(
                $building instanceof Building ? 'messages.doorAssignmentSms.withBuilding' : 'messages.doorAssignmentSms.body',
                [
                    'app' => (string) config('app.name'),
                    'reference' => $this->checkIn->reference_number,
                    'dock' => $this->dock->name,
                    'building' => $building?->name,
                ],
            ),
        );
    }
}
