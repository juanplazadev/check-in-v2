<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The application's Spatie permissions. Access is always checked against
 * these, never against a role; roles only group them (see Role::permissions()).
 * Mirrored as Permission in resources/js/types/auth.ts.
 */
enum Permission: string
{
    case ViewOverview = 'view overview';
    case ViewLiveQueue = 'view live queue';
    case ViewCheckIns = 'view check-ins';
    case ViewAppointments = 'view appointments';
    case ViewUsers = 'view users';
    case ViewLocations = 'view locations';
    case ViewAllLocations = 'view all locations';
    case ManageLocationUsers = 'manage location users';
    case EditLocations = 'edit locations';
    case ManageLocationStatus = 'manage location status';
    case DeleteLocations = 'delete locations';
    case RestoreLocations = 'restore locations';
    case PauseCheckIns = 'pause check-ins';
    case PauseAppointments = 'pause appointments';
    case ViewNotifications = 'view notifications';
    case SearchDashboard = 'search dashboard';
    case FilterCheckIns = 'filter check-ins';
    case FilterAppointments = 'filter appointments';
    case CompleteCheckIns = 'complete check-ins';
    case ReopenCheckIns = 'reopen check-ins';
    case SyncCheckInsToErp = 'sync check-ins to erp';
    case UnsyncCheckInsFromErp = 'unsync check-ins from erp';
    case EditCheckIns = 'edit check-ins';
    case LinkCheckInAppointments = 'link check-in appointments';
    case ViewCheckInPdf = 'view check-in pdf';
    case PrintCheckInPdf = 'print check-in pdf';
    case EmailCheckInPdf = 'email check-in pdf';
    case ViewAppointmentPdf = 'view appointment pdf';
    case PrintAppointmentPdf = 'print appointment pdf';
    case EmailAppointmentPdf = 'email appointment pdf';
    case CancelCheckIns = 'cancel check-ins';
    case ReinstateCheckIns = 'reinstate check-ins';
    case CancelAppointments = 'cancel appointments';
    case ReinstateAppointments = 'reinstate appointments';
    case WriteCheckInNotes = 'write check-in notes';
    case WriteAppointmentNotes = 'write appointment notes';
    case RevealDriverLicenses = 'reveal driver licenses';
    case DeleteCheckIns = 'delete check-ins';
    case DeleteAppointments = 'delete appointments';
    case ArchiveCheckIns = 'archive check-ins';
    case RestoreCheckIns = 'restore check-ins';
    case ArchiveAppointments = 'archive appointments';
    case RestoreAppointments = 'restore appointments';
    case ExportCheckInsView = 'export check-ins view';
    case ExportCheckInsMaster = 'export check-ins master';
    case ExportAppointmentsView = 'export appointments view';
    case ExportAppointmentsMaster = 'export appointments master';
    case SaveCheckInColumnDisplay = 'save check-in column display';
    case SaveCheckInColumnOrder = 'save check-in column order';
    case SaveAppointmentColumnDisplay = 'save appointment column display';
    case SaveAppointmentColumnOrder = 'save appointment column order';
    case ViewRolesAndPermissions = 'view roles and permissions';
    case AccessDriverAccount = 'access driver account';
}
