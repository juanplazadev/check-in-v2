<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What kind of thing happened to a check-in, a booking or a location.
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
    case NotificationSkipped = 'notification-skipped';
    case EmployeeNotificationQueued = 'employee-notification-queued';
    case StatusChanged = 'status-changed';
    case Claimed = 'claimed';
    case AccountDeleted = 'account-deleted';
    case NoteAdded = 'note-added';
    case LicenseViewed = 'license-viewed';
    case DetailsEdited = 'details-edited';
    case Reinstated = 'reinstated';
    case Archived = 'archived';
    case Restored = 'restored';
    case CheckInDeleted = 'check-in-deleted';
    case AppointmentDeleted = 'appointment-deleted';
    case AppointmentLinked = 'appointment-linked';
    case AppointmentUnlinked = 'appointment-unlinked';
    case PdfEmailed = 'pdf-emailed';
    case LocationUserAttached = 'location-user-attached';
    case LocationUserDetached = 'location-user-detached';
    case CheckInsPaused = 'check-ins-paused';
    case CheckInsResumed = 'check-ins-resumed';
    case AppointmentsPaused = 'appointments-paused';
    case AppointmentsResumed = 'appointments-resumed';
    case LocationDeleted = 'location-deleted';
    case LocationRestored = 'location-restored';
    case CheckInHoursEdited = 'check-in-hours-edited';
    case AppointmentHoursEdited = 'appointment-hours-edited';
    case CheckInOptionsEdited = 'check-in-options-edited';
    case AppointmentOptionsEdited = 'appointment-options-edited';
    case ClosureAdded = 'closure-added';
    case ClosureRemoved = 'closure-removed';
}
