<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Models\Appointment;
use App\Models\User;
use App\Notifications\AppointmentReceived;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Everything that goes when an appointment is permanently deleted, as counts
 * for the confirm dialog. The child-row queries live here so the dialog and
 * {@see \App\Actions\DeleteAppointmentAction} cannot drift apart: what the
 * admin is shown is exactly what gets deleted.
 */
final readonly class AppointmentDeletionImpact
{
    /**
     * @return array{historyEntries: int, notes: int, purchaseOrders: int, staffNotifications: int, hasShortLink: bool, pendingClaims: int, checkInReference: string|null, driverHasAccount: bool, archived: bool}
     */
    public function summary(Appointment $appointment): array
    {
        return [
            'historyEntries' => $appointment->history()->count(),
            'notes' => $appointment->notes()->count(),
            'purchaseOrders' => $appointment->purchaseOrders()->count(),
            'staffNotifications' => $this->staffNotifications($appointment)->count(),
            'hasShortLink' => $appointment->shortLink()->exists(),
            'pendingClaims' => $this->pendingClaims($appointment)->count(),
            'checkInReference' => $appointment->checkIn()->withTrashed()->value('reference_number'),
            'driverHasAccount' => $appointment->user_id !== null,
            'archived' => $appointment->trashed(),
        ];
    }

    /**
     * The staff bell rows announcing this booking. The payload is a JSON text
     * column, so the match is on the encoded uuid.
     *
     * @return Builder<DatabaseNotification>
     */
    public function staffNotifications(Appointment $appointment): Builder
    {
        return DatabaseNotification::query()
            ->where('type', AppointmentReceived::TYPE)
            ->where('data', 'like', '%"appointmentUuid":"'.$appointment->uuid.'"%');
    }

    /**
     * Accounts registered from this booking's claim link that have not yet
     * claimed it. The column has no foreign key, so nothing clears it.
     *
     * @return Builder<User>
     */
    public function pendingClaims(Appointment $appointment): Builder
    {
        return User::query()->where('pending_appointment_id', $appointment->id);
    }
}
