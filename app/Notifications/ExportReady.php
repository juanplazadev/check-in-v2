<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\ExportSubject;
use App\Models\User;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Confirms in the bell of the staff member who asked for a spreadsheet export
 * that it was emailed to them, or that it was too large to attach. Sent from
 * inside the export job, so it is not queued again.
 */
final class ExportReady extends Notification
{
    /**
     * Stored in the `type` column, which the bell switches on. Mirrored in
     * NotificationsMenu.tsx.
     */
    public const string TYPE = 'export.ready';

    public function __construct(
        public ExportSubject $subject,
        public string $filename,
        public int $rowCount,
        public bool $attached,
    ) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function databaseType(User $notifiable): string
    {
        return self::TYPE;
    }

    /**
     * @return array{subject: string, filename: string, rowCount: int, attached: bool}
     */
    public function toArray(User $notifiable): array
    {
        return [
            'subject' => $this->subject->value,
            'filename' => $this->filename,
            'rowCount' => $this->rowCount,
            'attached' => $this->attached,
        ];
    }

    public function toBroadcast(User $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
