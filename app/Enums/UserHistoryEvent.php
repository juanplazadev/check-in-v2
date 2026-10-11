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
}
