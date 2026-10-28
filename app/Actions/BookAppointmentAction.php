<?php

declare(strict_types=1);

namespace App\Actions;

use App\Appointment\AppointmentSlotCapacity;
use App\DTOs\AppointmentLocationDTO;
use App\Events\AppointmentCreated;
use App\Models\Appointment;
use App\Models\Location;
use App\Models\User;
use App\Notifications\AppointmentReceived;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final readonly class BookAppointmentAction
{
    public function __construct(
        private CreateAppointmentAction $createAppointment,
        private CreatePurchaseOrdersAction $createPurchaseOrders,
        private SendAppointmentBookedEmailAction $sendAppointmentBookedEmail,
        private SendAppointmentCopyAction $sendAppointmentCopy,
        private NotifyStaffAction $notifyStaff,
        private AppointmentSlotCapacity $slotCapacity,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     *
     * @throws ValidationException When the slot filled up after the form request checked it.
     * @throws Throwable
     */
    public function handle(array $validated, AppointmentLocationDTO $locationDTO, ?User $user = null): Appointment
    {
        $appointment = DB::transaction(function () use ($validated, $locationDTO, $user): Appointment {
            // Locked so two drivers booking the last spot in a slot cannot both pass the check.
            $location = Location::query()->where('uuid', $locationDTO->id)->lockForUpdate()->firstOrFail();

            if (! $this->slotCapacity->hasRoom($location, Date::parse($validated['datetime'], $location->timezone))) {
                throw ValidationException::withMessages(['datetime' => __('messages.appointmentBooking.slotFull')]);
            }

            $appointment = $this->createAppointment->handle($validated, $locationDTO, $user);
            $this->createPurchaseOrders->handle($appointment, $validated['po_numbers']);

            return $appointment;
        });

        // Queued after the commit so the notification never references an
        // appointment that was rolled back.
        $this->sendAppointmentBookedEmail->handle($appointment);

        // The driver's own copy, if they are signed in and have asked for it.
        $this->sendAppointmentCopy->handle($appointment);

        // Into the notification bell of every staff member who works this location.
        $this->notifyStaff->handle(new AppointmentReceived($appointment), $appointment->location_id);

        // Onto the live queue's arriving rail.
        event(new AppointmentCreated($appointment));

        return $appointment;
    }
}
