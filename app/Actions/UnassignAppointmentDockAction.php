<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\RecordHistoryEvent;
use App\Enums\UserHistoryEvent;
use App\Models\Appointment;
use App\Models\Dock;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Takes an appointment off its dock, freeing the door for another booking at
 * that time. An appointment with no dock is left alone and nothing is logged.
 */
final readonly class UnassignAppointmentDockAction
{
    public function __construct(
        private RecordHistoryAction $recordHistory,
        private RecordUserHistoryAction $recordUserHistory,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(Appointment $appointment, User $user): Appointment
    {
        return DB::transaction(function () use ($appointment, $user): Appointment {
            $appointment = Appointment::query()->lockForUpdate()->with('dock.building')->findOrFail($appointment->id);

            if (! $appointment->dock instanceof Dock) {
                return $appointment;
            }

            $from = $appointment->dock->label();

            $appointment->forceFill([
                'dock_id' => null,
                'dock_assigned_at' => null,
                'dock_assigned_by' => null,
            ])->save();

            $this->recordHistory->handle($appointment, RecordHistoryEvent::DockUnassigned, user: $user, context: ['from' => $from]);

            $this->recordUserHistory->handle($user, UserHistoryEvent::AppointmentDockAssigned, [
                'appointment' => $appointment->uuid,
                'from' => $from,
            ]);

            return $appointment->setRelation('dock', null);
        });
    }
}
