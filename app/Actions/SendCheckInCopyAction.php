<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\CheckIn;
use App\Models\User;
use App\Notifications\CheckInCopy;
use Illuminate\Support\Facades\Notification;

final readonly class SendCheckInCopyAction
{
    /**
     * Send the driver their own copy of a check-in.
     *
     * A registered driver gets it on whichever channels they chose, subject to
     * their notify_check_in_copy preference. A guest gets a text, always: the
     * check-in form requires a cellphone and collects no email, so a text is
     * both the only way to reach them and the only thing they could have
     * expected. There is no preference to consult because there is no account
     * to hold one.
     */
    public function handle(CheckIn $checkIn): void
    {
        $checkIn->loadMissing('user');

        $user = $checkIn->user;

        // The locale captured at check-in, so the worker renders the language
        // the driver was using. Covers the text as well as the email.
        $notification = new CheckInCopy($checkIn)->locale($checkIn->locale);

        // Read off the persisted record rather than the ?User threaded through
        // the caller, so the guard reflects what was actually saved.
        if ($user instanceof User) {
            if ($user->notify_check_in_copy) {
                $user->notify($notification);
            }

            return;
        }

        Notification::route('sms', $checkIn->drivers_cellphone)->notify($notification);
    }
}
