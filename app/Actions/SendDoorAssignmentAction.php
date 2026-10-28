<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CheckInStatus;
use App\Models\CheckIn;
use App\Models\Dock;
use App\Notifications\DoorAssignment;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Texts the driver the door their check-in is on, in the language they
 * checked in with. Always to the cellphone on the check-in: the driver is
 * in the yard, so this is not a copy their account preferences govern.
 */
final readonly class SendDoorAssignmentAction
{
    /**
     * @throws ValidationException When the check-in has no door or is no longer waiting.
     */
    public function handle(CheckIn $checkIn): void
    {
        $checkIn->loadMissing('dock.building');

        if (! $checkIn->dock instanceof Dock || $checkIn->status !== CheckInStatus::Pending) {
            throw ValidationException::withMessages(['dock' => 'Only a waiting truck with a door can be texted its door.']);
        }

        Notification::route('sms', $checkIn->drivers_cellphone)
            ->notify(new DoorAssignment($checkIn, $checkIn->dock)->locale($checkIn->locale));

        $checkIn->forceFill(['door_notified_at' => Date::now()])->save();
    }
}
