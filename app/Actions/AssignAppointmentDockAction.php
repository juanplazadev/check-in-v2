<?php

declare(strict_types=1);

namespace App\Actions;

use App\Appointment\AppointmentSlotCapacity;
use App\Enums\AppointmentStatus;
use App\Enums\RecordHistoryEvent;
use App\Enums\UserHistoryEvent;
use App\Models\Appointment;
use App\Models\Dock;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Places a booked appointment on one of its location's docks, or moves it to
 * another, and logs it on the appointment's timeline and the staff member's
 * trail. The save broadcasts the change to open dashboards through the
 * model's updated() hook.
 */
final readonly class AssignAppointmentDockAction
{
    public function __construct(
        private RecordHistoryAction $recordHistory,
        private RecordUserHistoryAction $recordUserHistory,
    ) {}

    /**
     * @throws ValidationException When the appointment is not live, or the dock is not open or not free.
     * @throws Throwable
     */
    public function handle(Appointment $appointment, User $user, string $dockUuid): Appointment
    {
        return DB::transaction(function () use ($appointment, $user, $dockUuid): Appointment {
            // Locked so two staff cannot place different bookings on the same dock at once.
            Location::query()->lockForUpdate()->findOrFail($appointment->location_id);

            $appointment = Appointment::query()->with('dock.building')->findOrFail($appointment->id);

            if (! in_array($appointment->status, [AppointmentStatus::Scheduled, AppointmentStatus::CheckedIn], true)) {
                throw ValidationException::withMessages(['dock' => 'Only a scheduled or checked-in appointment can be placed on a dock.']);
            }

            $dock = Dock::query()
                ->where('location_id', $appointment->location_id)
                ->where('uuid', $dockUuid)
                ->with('building')
                ->first();

            if (! $dock instanceof Dock || ! $dock->is_active || ! $dock->accepts_appointments) {
                throw ValidationException::withMessages(['dock' => 'Choose an open dock at this appointment\'s location.']);
            }

            if ($appointment->dock_id === $dock->id) {
                return $appointment;
            }

            $holder = Appointment::query()
                ->where('dock_id', $dock->id)
                ->where('scheduled_for', $appointment->scheduled_for)
                ->whereKeyNot($appointment->id)
                ->whereIn('status', AppointmentSlotCapacity::OCCUPYING_STATUSES)
                ->value('reference_number');

            if ($holder !== null) {
                throw ValidationException::withMessages([
                    'dock' => sprintf('%s already holds %s at this time.', $holder, $dock->name),
                ]);
            }

            $from = $appointment->dock?->label();

            $appointment->forceFill([
                'dock_id' => $dock->id,
                'dock_assigned_at' => Date::now(),
                'dock_assigned_by' => $user->id,
            ])->save();

            $context = array_filter(['from' => $from, 'to' => $dock->label()], fn (?string $value): bool => $value !== null);

            $this->recordHistory->handle($appointment, RecordHistoryEvent::DockAssigned, user: $user, context: $context);

            $this->recordUserHistory->handle($user, UserHistoryEvent::AppointmentDockAssigned, [
                'appointment' => $appointment->uuid,
                ...$context,
            ]);

            return $appointment->setRelation('dock', $dock);
        });
    }
}
