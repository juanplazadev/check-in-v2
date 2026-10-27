<?php

declare(strict_types=1);

namespace App\Enums;

enum UserHistoryEvent: string
{
    case ProfileUpdated = 'profile-updated';
    case PasswordUpdated = 'password-updated';
    case PasswordReset = 'password-reset';
    case CheckInNoteAdded = 'check-in-note-added';
    case CheckInCompleted = 'check-in-completed';
    case CheckInLicenseViewed = 'check-in-license-viewed';
    case CheckInReopened = 'check-in-reopened';
    case CheckInCancelled = 'check-in-cancelled';
    case CheckInReinstated = 'check-in-reinstated';
    case CheckInArchived = 'check-in-archived';
    case CheckInRestored = 'check-in-restored';
    case CheckInEdited = 'check-in-edited';
    case CheckInDeleted = 'check-in-deleted';
    case CheckInAppointmentLinked = 'check-in-appointment-linked';
    case CheckInAppointmentUnlinked = 'check-in-appointment-unlinked';
    case CheckInPdfEmailed = 'check-in-pdf-emailed';
    case AppointmentNoteAdded = 'appointment-note-added';
    case AppointmentCancelled = 'appointment-cancelled';
    case AppointmentReinstated = 'appointment-reinstated';
    case AppointmentArchived = 'appointment-archived';
    case AppointmentRestored = 'appointment-restored';
    case AppointmentPdfEmailed = 'appointment-pdf-emailed';
    case AppointmentDeleted = 'appointment-deleted';
    case LocationCreated = 'location-created';
    case LocationUserAttached = 'location-user-attached';
    case LocationUserDetached = 'location-user-detached';
    case LocationEdited = 'location-edited';
    case LocationCheckInsPaused = 'location-check-ins-paused';
    case LocationCheckInsResumed = 'location-check-ins-resumed';
    case LocationAppointmentsPaused = 'location-appointments-paused';
    case LocationAppointmentsResumed = 'location-appointments-resumed';
    case LocationDeleted = 'location-deleted';
    case LocationRestored = 'location-restored';
    case LocationCheckInHoursEdited = 'location-check-in-hours-edited';
    case LocationAppointmentHoursEdited = 'location-appointment-hours-edited';
    case LocationCheckInOptionsEdited = 'location-check-in-options-edited';
    case LocationAppointmentOptionsEdited = 'location-appointment-options-edited';
    case LocationClosureAdded = 'location-closure-added';
    case LocationClosureRemoved = 'location-closure-removed';
    case RolePermissionsEdited = 'role-permissions-edited';
    case UserCreated = 'user-created';
    case InvitationAccepted = 'invitation-accepted';
    case QueueWorkersRestarted = 'queue-workers-restarted';
    case QueuePaused = 'queue-paused';
    case QueueResumed = 'queue-resumed';
    case FailedJobRetried = 'failed-job-retried';
    case FailedJobsRetried = 'failed-jobs-retried';
    case FailedJobDeleted = 'failed-job-deleted';

    /**
     * How the dashboard's activity table reads the event, as something the
     * user did.
     */
    public function label(): string
    {
        return match ($this) {
            self::ProfileUpdated => 'Updated their profile',
            self::PasswordUpdated => 'Changed their password',
            self::PasswordReset => 'Reset their password',
            self::CheckInNoteAdded => 'Added a check-in note',
            self::CheckInCompleted => 'Completed a check-in',
            self::CheckInLicenseViewed => "Viewed a driver's license",
            self::CheckInReopened => 'Reopened a check-in',
            self::CheckInCancelled => 'Canceled a check-in',
            self::CheckInReinstated => 'Reinstated a check-in',
            self::CheckInArchived => 'Archived a check-in',
            self::CheckInRestored => 'Restored a check-in',
            self::CheckInEdited => 'Edited a check-in',
            self::CheckInDeleted => 'Deleted a check-in',
            self::CheckInAppointmentLinked => 'Linked an appointment to a check-in',
            self::CheckInAppointmentUnlinked => 'Unlinked an appointment from a check-in',
            self::CheckInPdfEmailed => 'Emailed a check-in PDF',
            self::AppointmentNoteAdded => 'Added an appointment note',
            self::AppointmentCancelled => 'Canceled an appointment',
            self::AppointmentReinstated => 'Reinstated an appointment',
            self::AppointmentArchived => 'Archived an appointment',
            self::AppointmentRestored => 'Restored an appointment',
            self::AppointmentPdfEmailed => 'Emailed an appointment PDF',
            self::AppointmentDeleted => 'Deleted an appointment',
            self::LocationCreated => 'Created a location',
            self::LocationUserAttached => 'Added a user to a location',
            self::LocationUserDetached => 'Removed a user from a location',
            self::LocationEdited => 'Edited location details',
            self::LocationCheckInsPaused => 'Paused check-ins',
            self::LocationCheckInsResumed => 'Resumed check-ins',
            self::LocationAppointmentsPaused => 'Paused appointments',
            self::LocationAppointmentsResumed => 'Resumed appointments',
            self::LocationDeleted => 'Deleted a location',
            self::LocationRestored => 'Restored a location',
            self::LocationCheckInHoursEdited => 'Edited check-in hours',
            self::LocationAppointmentHoursEdited => 'Edited appointment hours',
            self::LocationCheckInOptionsEdited => 'Edited check-in options',
            self::LocationAppointmentOptionsEdited => 'Edited appointment options',
            self::LocationClosureAdded => 'Added a closure',
            self::LocationClosureRemoved => 'Removed a closure',
            self::RolePermissionsEdited => 'Edited role permissions',
            self::UserCreated => 'Added a user',
            self::InvitationAccepted => 'Accepted their invitation',
            self::QueueWorkersRestarted => 'Restarted the queue workers',
            self::QueuePaused => 'Paused the queue workers',
            self::QueueResumed => 'Resumed the queue workers',
            self::FailedJobRetried => 'Retried a failed job',
            self::FailedJobsRetried => 'Retried all failed jobs',
            self::FailedJobDeleted => 'Deleted a failed job',
        };
    }
}
