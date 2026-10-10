<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\AppointmentReceived;
use App\Notifications\CheckInReceived;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Notifications\DatabaseNotification;

/**
 * The header notification bell. Every lookup goes through the signed-in user's
 * own notifications, so another user's id is a 404.
 */
final class NotificationController extends Controller
{
    /**
     * Mark one notification read and open whatever it is about.
     */
    public function read(#[CurrentUser] User $user, string $id): RedirectResponse
    {
        /** @var DatabaseNotification $notification */
        $notification = $user->notifications()->findOrFail($id);

        $notification->markAsRead();

        return redirect()->to($this->targetUrl($notification));
    }

    public function readAll(#[CurrentUser] User $user): RedirectResponse
    {
        $user->unreadNotifications->markAsRead();

        return back();
    }

    private function targetUrl(DatabaseNotification $notification): string
    {
        return match ($notification->type) {
            CheckInReceived::TYPE => route('dashboard.checkIns.show', $notification->data['checkInUuid']),
            AppointmentReceived::TYPE => route('dashboard.appointments.show', $notification->data['appointmentUuid']),
            default => route('dashboard'),
        };
    }
}
