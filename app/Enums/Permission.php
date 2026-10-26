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
    case ViewAnalytics = 'view analytics';
    case ViewUsers = 'view users';
    case CreateUsers = 'create users';
    case ViewLocations = 'view locations';
    case ViewAllLocations = 'view all locations';
    case CreateLocations = 'create locations';
    case ManageLocationUsers = 'manage location users';
    case EditLocations = 'edit locations';
    case ManageLocationStatus = 'manage location status';
    case DeleteLocations = 'delete locations';
    case RestoreLocations = 'restore locations';
    case PauseCheckIns = 'pause check-ins';
    case PauseAppointments = 'pause appointments';
    case EditLocationHours = 'edit location hours';
    case EditCheckInOptions = 'edit check-in options';
    case EditAppointmentOptions = 'edit appointment options';
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
    case EditRolesAndPermissions = 'edit roles and permissions';
    case ViewErrors = 'view errors';
    case ViewDeployments = 'view deployments';
    case AccessDriverAccount = 'access driver account';

    /**
     * The permission as the matrix names it: "view check-in pdf" reads as
     * "View check-in PDF".
     */
    public function label(): string
    {
        return ucfirst((string) preg_replace_callback(
            '/\b(erp|pdf)\b/',
            fn (array $match): string => mb_strtoupper($match[1]),
            $this->value,
        ));
    }

    /**
     * The section of the roles and permissions matrix this permission is
     * listed under.
     */
    public function group(): PermissionGroup
    {
        return match ($this) {
            self::ViewOverview,
            self::ViewLiveQueue,
            self::ViewAnalytics,
            self::ViewNotifications,
            self::SearchDashboard => PermissionGroup::Dashboard,
            self::ViewCheckIns,
            self::FilterCheckIns,
            self::CompleteCheckIns,
            self::ReopenCheckIns,
            self::SyncCheckInsToErp,
            self::UnsyncCheckInsFromErp,
            self::EditCheckIns,
            self::LinkCheckInAppointments,
            self::CancelCheckIns,
            self::ReinstateCheckIns,
            self::WriteCheckInNotes,
            self::RevealDriverLicenses,
            self::ArchiveCheckIns,
            self::RestoreCheckIns,
            self::DeleteCheckIns => PermissionGroup::CheckIns,
            self::ViewAppointments,
            self::FilterAppointments,
            self::CancelAppointments,
            self::ReinstateAppointments,
            self::WriteAppointmentNotes,
            self::ArchiveAppointments,
            self::RestoreAppointments,
            self::DeleteAppointments => PermissionGroup::Appointments,
            self::ViewCheckInPdf,
            self::PrintCheckInPdf,
            self::EmailCheckInPdf,
            self::ViewAppointmentPdf,
            self::PrintAppointmentPdf,
            self::EmailAppointmentPdf => PermissionGroup::Documents,
            self::ExportCheckInsView,
            self::ExportCheckInsMaster,
            self::ExportAppointmentsView,
            self::ExportAppointmentsMaster,
            self::SaveCheckInColumnDisplay,
            self::SaveCheckInColumnOrder,
            self::SaveAppointmentColumnDisplay,
            self::SaveAppointmentColumnOrder => PermissionGroup::ExportsAndColumns,
            self::ViewLocations,
            self::ViewAllLocations,
            self::CreateLocations,
            self::EditLocations,
            self::ManageLocationUsers,
            self::ManageLocationStatus,
            self::EditLocationHours,
            self::EditCheckInOptions,
            self::EditAppointmentOptions,
            self::PauseCheckIns,
            self::PauseAppointments,
            self::DeleteLocations,
            self::RestoreLocations => PermissionGroup::Locations,
            self::ViewUsers,
            self::CreateUsers,
            self::ViewRolesAndPermissions,
            self::EditRolesAndPermissions,
            self::ViewErrors,
            self::ViewDeployments => PermissionGroup::Administration,
            self::AccessDriverAccount => PermissionGroup::Driver,
        };
    }

    /**
     * What the permission lets its holder do, as the matrix explains it.
     */
    public function description(): string
    {
        return match ($this) {
            self::ViewOverview => 'Open the dashboard overview.',
            self::ViewLiveQueue => 'Watch the live check-in queue.',
            self::ViewAnalytics => 'Open the analytics page.',
            self::ViewNotifications => 'Receive and read dashboard notifications.',
            self::SearchDashboard => 'Search check-ins, appointments and locations.',
            self::ViewCheckIns => 'Open the check-ins table and each check-in.',
            self::FilterCheckIns => 'Filter the check-ins table.',
            self::CompleteCheckIns => 'Mark a check-in as completed.',
            self::ReopenCheckIns => 'Reopen a completed check-in.',
            self::SyncCheckInsToErp => 'Mark a check-in as synced to the ERP.',
            self::UnsyncCheckInsFromErp => 'Undo a check-in\'s ERP sync.',
            self::EditCheckIns => 'Change the details a driver entered.',
            self::LinkCheckInAppointments => 'Link or unlink a check-in and an appointment.',
            self::CancelCheckIns => 'Cancel a check-in.',
            self::ReinstateCheckIns => 'Reinstate a canceled check-in.',
            self::WriteCheckInNotes => 'Add notes to a check-in.',
            self::RevealDriverLicenses => 'Show a driver\'s full license number.',
            self::ArchiveCheckIns => 'Move a check-in to the archive.',
            self::RestoreCheckIns => 'Bring a check-in back from the archive.',
            self::DeleteCheckIns => 'Delete a check-in for good.',
            self::ViewAppointments => 'Open the appointments table and each appointment.',
            self::FilterAppointments => 'Filter the appointments table.',
            self::CancelAppointments => 'Cancel an appointment.',
            self::ReinstateAppointments => 'Reinstate a canceled appointment.',
            self::WriteAppointmentNotes => 'Add notes to an appointment.',
            self::ArchiveAppointments => 'Move an appointment to the archive.',
            self::RestoreAppointments => 'Bring an appointment back from the archive.',
            self::DeleteAppointments => 'Delete an appointment for good.',
            self::ViewCheckInPdf => 'Open a check-in\'s PDF.',
            self::PrintCheckInPdf => 'Print a check-in\'s PDF.',
            self::EmailCheckInPdf => 'Email a check-in\'s PDF to someone.',
            self::ViewAppointmentPdf => 'Open an appointment\'s PDF.',
            self::PrintAppointmentPdf => 'Print an appointment\'s PDF.',
            self::EmailAppointmentPdf => 'Email an appointment\'s PDF to someone.',
            self::ExportCheckInsView => 'Export the check-ins table as shown.',
            self::ExportCheckInsMaster => 'Export every check-in with every column.',
            self::ExportAppointmentsView => 'Export the appointments table as shown.',
            self::ExportAppointmentsMaster => 'Export every appointment with every column.',
            self::SaveCheckInColumnDisplay => 'Save which check-in columns are shown.',
            self::SaveCheckInColumnOrder => 'Save the order of the check-in columns.',
            self::SaveAppointmentColumnDisplay => 'Save which appointment columns are shown.',
            self::SaveAppointmentColumnOrder => 'Save the order of the appointment columns.',
            self::ViewLocations => 'Open the locations they are attached to.',
            self::ViewAllLocations => 'See every location, not only the attached ones.',
            self::CreateLocations => 'Add a new location.',
            self::EditLocations => 'Change a location\'s details.',
            self::ManageLocationUsers => 'Attach staff to a location or remove them.',
            self::ManageLocationStatus => 'Turn a location, its check-ins or appointments on or off.',
            self::EditLocationHours => 'Change opening hours and closures.',
            self::EditCheckInOptions => 'Change a location\'s check-in settings.',
            self::EditAppointmentOptions => 'Change a location\'s booking settings.',
            self::PauseCheckIns => 'Pause new check-ins at a location.',
            self::PauseAppointments => 'Pause new bookings at a location.',
            self::DeleteLocations => 'Delete a location.',
            self::RestoreLocations => 'Bring back a deleted location.',
            self::ViewUsers => 'Open the users table and each user.',
            self::CreateUsers => 'Add staff accounts from the users page.',
            self::ViewRolesAndPermissions => 'Open this page.',
            self::EditRolesAndPermissions => 'Change which permissions each role grants.',
            self::ViewErrors => 'See application errors reported to Sentry.',
            self::ViewDeployments => 'See deployments, releases and their changelogs.',
            self::AccessDriverAccount => 'Use the driver account pages.',
        };
    }
}
