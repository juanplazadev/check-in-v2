<?php

declare(strict_types=1);

namespace App\Actions;

use App\Appointment\AppointmentScheduleResolver;
use App\Appointment\AppointmentSlotCapacity;
use App\Enums\AppointmentStatus;
use App\Enums\RecordHistoryEvent;
use App\Enums\UserHistoryEvent;
use App\Models\Appointment;
use App\Models\LoadType;
use App\Models\Location;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Changes a booked appointment's load type, so it holds its dock for the new
 * type's length. Staff use it to correct a booking that took the location's
 * default. The new end must still fit before closing, within the location's
 * capacity, and on the assigned dock. Logged on the appointment's timeline
 * and the staff member's trail; the save broadcasts through the model's
 * updated() hook.
 */
final readonly class ChangeAppointmentLoadTypeAction
{
    public function __construct(
        private AppointmentScheduleResolver $schedule,
        private AppointmentSlotCapacity $capacity,
        private RecordHistoryAction $recordHistory,
        private RecordUserHistoryAction $recordUserHistory,
    ) {}

    /**
     * @throws ValidationException When the appointment is not live, the location has no bookable docks, the load type is not open, or the longer booking does not fit.
     * @throws Throwable
     */
    public function handle(Appointment $appointment, User $user, string $loadTypeUuid): Appointment
    {
        return DB::transaction(function () use ($appointment, $user, $loadTypeUuid): Appointment {
            // Locked so a driver's booking cannot take the time this one grows into.
            $location = Location::query()->lockForUpdate()->findOrFail($appointment->location_id);

            $appointment = Appointment::query()->with(['loadType', 'dock'])->findOrFail($appointment->id);

            if (! in_array($appointment->status, [AppointmentStatus::Scheduled, AppointmentStatus::CheckedIn], true)) {
                throw ValidationException::withMessages(['load_type' => "Only a scheduled or checked-in appointment's load type can change."]);
            }

            if (! $location->hasBookableDocks()) {
                throw ValidationException::withMessages(['load_type' => 'Load types are off until this location has docks that take appointments.']);
            }

            $loadType = LoadType::query()
                ->where('location_id', $appointment->location_id)
                ->where('uuid', $loadTypeUuid)
                ->active()
                ->first();

            if (! $loadType instanceof LoadType) {
                throw ValidationException::withMessages(['load_type' => "Choose an active load type at this appointment's location."]);
            }

            if ($appointment->load_type_id === $loadType->id) {
                return $appointment;
            }

            $start = $appointment->scheduled_for;
            $end = $start->copy()->addMinutes($loadType->duration_minutes);

            $closesAt = $this->schedule->closesAt($location, $start->copy()->setTimezone($location->timezone)->toDateString());

            if ($closesAt instanceof CarbonInterface && $end->gt($closesAt)) {
                throw ValidationException::withMessages(['load_type' => 'This load would run past closing time.']);
            }

            if (! $this->capacity->hasRoom($location, $start, $end, ignore: $appointment)) {
                throw ValidationException::withMessages(['load_type' => 'Another booking needs a dock during that time. There is no room for a longer load.']);
            }

            if ($appointment->dock !== null) {
                $holder = $this->capacity->dockHolder($appointment->dock->id, $start, $end, ignore: $appointment);

                if ($holder !== null) {
                    throw ValidationException::withMessages([
                        'load_type' => sprintf('%s already holds %s during the new time.', $holder, $appointment->dock->name),
                    ]);
                }
            }

            $change = ['from' => $appointment->loadType?->name, 'to' => $loadType->name];

            $appointment->forceFill([
                'load_type_id' => $loadType->id,
                'ends_at' => $end,
            ])->save();

            $this->recordHistory->handle($appointment, RecordHistoryEvent::LoadTypeChanged, user: $user, context: [
                'load_type' => $change,
            ]);

            $this->recordUserHistory->handle($user, UserHistoryEvent::AppointmentLoadTypeChanged, [
                'appointment' => $appointment->uuid,
                'load_type' => $change,
            ]);

            return $appointment->setRelation('loadType', $loadType);
        });
    }
}
