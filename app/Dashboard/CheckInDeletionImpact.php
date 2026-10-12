<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Models\CheckIn;
use App\Models\User;
use App\Notifications\CheckInReceived;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Everything that goes when a check-in is permanently deleted, as counts for
 * the confirm dialog. The child-row queries live here so the dialog and
 * {@see \App\Actions\DeleteCheckInAction} cannot drift apart: what the admin is
 * shown is exactly what gets deleted.
 */
final readonly class CheckInDeletionImpact
{
    /**
     * @return array{historyEntries: int, notes: int, purchaseOrders: int, staffNotifications: int, hasShortLink: bool, pendingClaims: int, appointmentReference: string|null, driverHasAccount: bool, archived: bool}
     */
    public function summary(CheckIn $checkIn): array
    {
        return [
            'historyEntries' => $checkIn->history()->count(),
            'notes' => $checkIn->notes()->count(),
            'purchaseOrders' => $checkIn->purchaseOrders()->count(),
            'staffNotifications' => $this->staffNotifications($checkIn)->count(),
            'hasShortLink' => $checkIn->shortLink()->exists(),
            'pendingClaims' => $this->pendingClaims($checkIn)->count(),
            'appointmentReference' => $checkIn->appointment?->reference_number,
            'driverHasAccount' => $checkIn->user_id !== null,
            'archived' => $checkIn->trashed(),
        ];
    }

    /**
     * The staff bell rows announcing this check-in. The payload is a JSON text
     * column, so the match is on the encoded uuid.
     *
     * @return Builder<DatabaseNotification>
     */
    public function staffNotifications(CheckIn $checkIn): Builder
    {
        return DatabaseNotification::query()
            ->where('type', CheckInReceived::TYPE)
            ->where('data', 'like', '%"checkInUuid":"'.$checkIn->uuid.'"%');
    }

    /**
     * Accounts registered from this check-in's claim link that have not yet
     * claimed it. The column has no foreign key, so nothing clears it.
     *
     * @return Builder<User>
     */
    public function pendingClaims(CheckIn $checkIn): Builder
    {
        return User::query()->where('pending_check_in_id', $checkIn->id);
    }
}
