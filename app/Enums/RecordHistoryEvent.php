<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What kind of thing happened to a check-in or a booking.
 *
 * Deliberately small and stable. Which message went out is carried by the
 * `subject` slug, not by a case here, so a new notification type never needs a
 * new case or a migration.
 */
enum RecordHistoryEvent: string
{
    case Created = 'created';
    case NotificationSent = 'notification-sent';
    case NotificationFailed = 'notification-failed';
    case EmployeeNotificationQueued = 'employee-notification-queued';
    case StatusChanged = 'status-changed';
    case Claimed = 'claimed';
    case AccountDeleted = 'account-deleted';
    case NoteAdded = 'note-added';
    case LicenseViewed = 'license-viewed';
}
