<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class CancelAppointmentAction
{
    public function __construct(
        private SendAppointmentCancelledEmailAction $sendAppointmentCancelledEmail,
        private SendAppointmentCancellationAction $sendAppointmentCancellation,
    ) {}

    /**
     * Cancel a booking at the driver's request.
     *
     * The status write is what produces the admin trail: the saved() hook in
     * AppServiceProvider records a StatusChanged row for any status change, so
     * nothing is written by hand here.
     *
     * @throws Throwable
     */
    public function handle(Appointment $appointment, string $reason): Appointment
    {
        DB::transaction(function () use ($appointment, $reason): void {
            $appointment->forceFill([
                'status' => AppointmentStatus::Cancelled,
                'cancelled_at' => Date::now(),
                'cancelled_reason' => $reason,
            ])->save();
        });

        // Queued after the commit, for the same reason BookAppointmentAction
        // sends outside its transaction: a rolled-back cancellation must never
        // reach the dock or the driver.
        $this->sendAppointmentCancelledEmail->handle($appointment);
        $this->sendAppointmentCancellation->handle($appointment);

        return $appointment;
    }

    /**
     * Whether the driver is still allowed to cancel this booking themselves.
     *
     * Two conditions, and neither is a lead-time cutoff: the booking has to
     * still be standing, and it has to still be ahead of us. Cancelling a slot
     * that has already passed tells the dock nothing they cannot see, and would
     * let a driver rewrite the record of a no-show after the fact.
     */
    public function isCancellable(Appointment $appointment): bool
    {
        return $appointment->status === AppointmentStatus::Scheduled
            && $appointment->scheduled_for->isFuture();
    }
}
