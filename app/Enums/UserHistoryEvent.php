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
}
