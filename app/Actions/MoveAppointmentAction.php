<?php

declare(strict_types=1);

namespace App\Actions;

use App\Appointment\AppointmentScheduleResolver;
use App\Appointment\AppointmentSlotCapacity;
use App\Appointment\ReminderSchedule;
use App\Enums\AppointmentStatus;
use App\Enums\RecordHistoryEvent;
use App\Enums\UserHistoryEvent;
use App\Models\Appointment;
use App\Models\Dock;
use App\Models\Location;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Moves a booking on the dock view: to a new time, a new dock, or both. One
 * drop can change both, so they are checked together against the final
 * position; checking the new dock at the old time could wrongly refuse it.
 *
 * A move keeps the booking's length, which was fixed when it was booked. A
 * time change tells the driver once it commits. The save broadcasts through
 * the model's updated() hook.
 */
final readonly class MoveAppointmentAction
{
    public function __construct(
        private AppointmentScheduleResolver $schedule,
        private AppointmentSlotCapacity $capacity,
        private ReminderSchedule $reminders,
        private RecordHistoryAction $recordHistory,
        private RecordUserHistoryAction $recordUserHistory,
        private SendAppointmentRescheduledAction $sendRescheduled,
    ) {}

    /**
     * @param  string|null  $dockUuid  The dock to end up on; null leaves the booking unassigned.
     *
     * @throws ValidationException When the appointment cannot move, or the new position does not fit.
     * @throws Throwable
     */
    public function handle(Appointment $appointment, User $user, CarbonInterface $start, ?string $dockUuid): Appointment
    {
        $previousStart = null;

        $appointment = DB::transaction(function () use ($appointment, $user, $start, $dockUuid, &$previousStart): Appointment {
            // Locked so a driver's booking or another move cannot take the same room.
            $location = Location::query()->lockForUpdate()->findOrFail($appointment->location_id);

            $appointment = Appointment::query()->with('dock.building')->findOrFail($appointment->id);
            $appointment->setRelation('location', $location);

            if (! in_array($appointment->status, [AppointmentStatus::Scheduled, AppointmentStatus::CheckedIn], true)) {
                throw ValidationException::withMessages(['move' => 'Only a scheduled or checked-in appointment can move.']);
            }

            $dock = $this->targetDock($appointment, $dockUuid);
            $movesTime = ! $start->equalTo($appointment->scheduled_for);
            $movesDock = $dock?->id !== $appointment->dock_id;

            if (! $movesTime && ! $movesDock) {
                return $appointment;
            }

            $end = $start->copy()->addSeconds((int) $appointment->scheduled_for->diffInSeconds($appointment->ends_at));

            if ($movesTime) {
                $this->ensureTimeFits($appointment, $location, $start, $end);
            }

            if ($dock instanceof Dock) {
                $holder = $this->capacity->dockHolder($dock->id, $start, $end, ignore: $appointment);

                if ($holder !== null) {
                    throw ValidationException::withMessages([
                        'move' => sprintf('%s already holds %s during that time.', $holder, $dock->name),
                    ]);
                }
            }

            $from = $appointment->scheduled_for;
            $fromDock = $appointment->dock?->label();

            if ($movesTime) {
                $appointment->forceFill([
                    'scheduled_for' => $start->copy()->utc(),
                    'ends_at' => $end->copy()->utc(),
                ]);

                // A reminder moment already gone is covered by the notice this move sends.
                $appointment->forceFill([
                    'reminder_sent_at' => $this->reminders->reminderMomentFor($appointment)->isFuture() ? null : Date::now(),
                ]);
            }

            if ($movesDock) {
                $appointment->forceFill([
                    'dock_id' => $dock?->id,
                    'dock_assigned_at' => $dock instanceof Dock ? Date::now() : null,
                    'dock_assigned_by' => $dock instanceof Dock ? $user->id : null,
                ]);
            }

            $appointment->save();

            if ($movesTime) {
                $previousStart = $from;
                $this->recordReschedule($appointment, $location, $user, $from);
            }

            if ($movesDock) {
                $this->recordDockChange($appointment, $user, $fromDock, $dock);
            }

            return $appointment->setRelation('dock', $dock);
        });

        if ($previousStart instanceof CarbonInterface) {
            $this->sendRescheduled->handle($appointment, $previousStart);
        }

        return $appointment;
    }

    /**
     * @throws ValidationException When the dock is not open at this location. The dock it is already on stays valid.
     */
    private function targetDock(Appointment $appointment, ?string $dockUuid): ?Dock
    {
        if ($dockUuid === null) {
            return null;
        }

        if ($appointment->dock?->uuid === $dockUuid) {
            return $appointment->dock;
        }

        $dock = Dock::query()
            ->where('location_id', $appointment->location_id)
            ->where('uuid', $dockUuid)
            ->with('building')
            ->bookable()
            ->first();

        if (! $dock instanceof Dock) {
            throw ValidationException::withMessages(['move' => "Choose an open dock at this appointment's location."]);
        }

        return $dock;
    }

    /**
     * @throws ValidationException When the booking cannot run at the new time.
     */
    private function ensureTimeFits(Appointment $appointment, Location $location, CarbonInterface $start, CarbonInterface $end): void
    {
        if ($appointment->status === AppointmentStatus::CheckedIn) {
            throw ValidationException::withMessages(['move' => 'This truck has already arrived, so only its dock can change.']);
        }

        if ($start->isPast()) {
            throw ValidationException::withMessages(['move' => 'Choose a time that has not passed.']);
        }

        if (! $this->schedule->fitsOpenHours($location, $start, $end)) {
            throw ValidationException::withMessages(['move' => "Choose a slot inside this location's hours."]);
        }

        if (! $this->capacity->hasRoom($location, $start, $end, ignore: $appointment)) {
            throw ValidationException::withMessages(['move' => 'Every dock is booked during that time.']);
        }
    }

    private function recordReschedule(Appointment $appointment, Location $location, User $user, CarbonInterface $from): void
    {
        $change = [
            'from' => $from->copy()->setTimezone($location->timezone)->format('M j, Y g:i A T'),
            'to' => $appointment->scheduled_for->setTimezone($location->timezone)->format('M j, Y g:i A T'),
        ];

        $this->recordHistory->handle($appointment, RecordHistoryEvent::Rescheduled, user: $user, context: [
            'scheduled_for' => $change,
        ]);

        $this->recordUserHistory->handle($user, UserHistoryEvent::AppointmentRescheduled, [
            'appointment' => $appointment->uuid,
            'scheduled_for' => $change,
        ]);
    }

    private function recordDockChange(Appointment $appointment, User $user, ?string $from, ?Dock $dock): void
    {
        $context = array_filter(['from' => $from, 'to' => $dock?->label()], fn (?string $value): bool => $value !== null);

        $this->recordHistory->handle(
            $appointment,
            $dock instanceof Dock ? RecordHistoryEvent::DockAssigned : RecordHistoryEvent::DockUnassigned,
            user: $user,
            context: $context,
        );

        $this->recordUserHistory->handle($user, UserHistoryEvent::AppointmentDockAssigned, [
            'appointment' => $appointment->uuid,
            ...$context,
        ]);
    }
}
