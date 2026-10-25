<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\AppointmentStatus;
use App\Enums\CheckInStatus;
use App\Enums\RecordHistoryEvent;
use App\Enums\UserHistoryEvent;
use App\Models\Appointment;
use App\Models\CheckIn;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Soft-deletes a location, with a required reason, and logs it on both the
 * location's timeline and the acting staff member's trail.
 *
 * The location leaves the dashboard and the driver and guest pickers at once.
 * Its past check-ins and appointments are kept and still show it, and its
 * attached staff are left alone, so a restore brings everything back as it
 * was. A location with a truck still on site or a booking still to come cannot
 * be deleted: those have to be handled first.
 */
final readonly class DeleteLocationAction
{
    public function __construct(
        private RecordHistoryAction $recordHistory,
        private RecordUserHistoryAction $recordUserHistory,
    ) {}

    /**
     * @throws ValidationException when the location has pending check-ins or upcoming appointments.
     * @throws Throwable
     */
    public function handle(Location $location, User $user, string $reason): Location
    {
        return DB::transaction(function () use ($location, $user, $reason): Location {
            // Locked so two admins clicking at once cannot delete it twice. An
            // already deleted location is out of the default scope: a 404.
            $location = Location::query()->lockForUpdate()->findOrFail($location->id);

            $pendingCheckIns = CheckIn::query()
                ->whereBelongsTo($location)
                ->where('status', CheckInStatus::Pending)
                ->count();

            $upcomingAppointments = Appointment::query()
                ->whereBelongsTo($location)
                ->where('status', AppointmentStatus::Scheduled)
                ->where('scheduled_for', '>=', now())
                ->count();

            if ($pendingCheckIns > 0 || $upcomingAppointments > 0) {
                throw ValidationException::withMessages([
                    'location' => sprintf(
                        '%s has %d pending %s and %d upcoming %s. Complete or cancel them before deleting it.',
                        $location->name,
                        $pendingCheckIns,
                        $pendingCheckIns === 1 ? 'check-in' : 'check-ins',
                        $upcomingAppointments,
                        $upcomingAppointments === 1 ? 'appointment' : 'appointments',
                    ),
                ]);
            }

            $location->delete();

            $this->recordHistory->handle($location, RecordHistoryEvent::LocationDeleted, user: $user, context: [
                'reason' => $reason,
            ]);

            $this->recordUserHistory->handle($user, UserHistoryEvent::LocationDeleted, [
                'location' => $location->uuid,
                'name' => $location->name,
                'reason' => $reason,
            ]);

            return $location;
        });
    }
}
