<?php

declare(strict_types=1);

namespace App\Actions;

use App\DTOs\AppointmentLocationDTO;
use App\Events\AppointmentCreated;
use App\Models\Appointment;
use App\Models\User;
use App\Notifications\AppointmentReceived;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class BookAppointmentAction
{
    public function __construct(
        private CreateAppointmentAction $createAppointment,
        private CreatePurchaseOrdersAction $createPurchaseOrders,
        private SendAppointmentBookedEmailAction $sendAppointmentBookedEmail,
        private SendAppointmentCopyAction $sendAppointmentCopy,
        private NotifyStaffAction $notifyStaff,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     *
     * @throws Throwable
     */
    public function handle(array $validated, AppointmentLocationDTO $locationDTO, ?User $user = null): Appointment
    {
        $appointment = DB::transaction(function () use ($validated, $locationDTO, $user): Appointment {

            $appointment = $this->createAppointment->handle($validated, $locationDTO, $user);
            $this->createPurchaseOrders->handle($appointment, $validated['po_numbers']);

            return $appointment;
        });

        // Queued after the commit so the notification never references an
        // appointment that was rolled back.
        $this->sendAppointmentBookedEmail->handle($appointment);

        // The driver's own copy, if they are signed in and have asked for it.
        $this->sendAppointmentCopy->handle($appointment);

        // Into every staff member's notification bell.
        $this->notifyStaff->handle(new AppointmentReceived($appointment));

        // Onto the live queue's arriving rail.
        AppointmentCreated::dispatch($appointment);

        return $appointment;
    }
}
