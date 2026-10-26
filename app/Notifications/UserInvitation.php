<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invites a staff member an admin created to choose their first password,
 * rendered with the branded resources/views/mail/user-invitation.blade.php
 * template. The link opens the invitation page, which redeems the token on
 * the invites broker.
 */
final class UserInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $token, public string $inviterName) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * @param  User  $notifiable
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('messages.userInvitationEmail.subject', ['app' => config('app.name')]))
            ->view('mail.user-invitation', [
                'url' => route('invitation.show', [
                    'token' => $this->token,
                    'email' => $notifiable->getEmailForPasswordReset(),
                ]),
                'name' => $notifiable->name,
                'inviter' => $this->inviterName,
                'expiresInDays' => intdiv((int) config('auth.passwords.invites.expire', 10080), 1440),
            ]);
    }
}
