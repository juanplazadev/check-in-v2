<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The application's Spatie roles. Global (teams are off): staff are tied to
 * locations separately, not through role scoping.
 */
enum Role: string
{
    case Driver = 'driver';
    case Admin = 'admin';
    case ShippingManager = 'shipping-manager';
    case Shipping = 'shipping';
    case Sales = 'sales';

    /**
     * Every employee role, i.e. everything but driver.
     *
     * @return list<self>
     */
    public static function staff(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $role): bool => $role !== self::Driver,
        ));
    }

    /**
     * Staff who see only the locations they are attached to (the
     * location_user pivot): every staff role whose default grants lack view
     * all locations. For seeding only: admins can move the permission, so
     * everything else checks it on the user.
     *
     * @return list<self>
     */
    public static function locationScoped(): array
    {
        return array_values(array_filter(
            self::staff(),
            fn (self $role): bool => ! in_array(Permission::ViewAllLocations, $role->permissions(), true),
        ));
    }

    /**
     * Staff who see every location, whatever they are attached to, by their
     * default grants. For seeding only, as locationScoped().
     *
     * @return list<self>
     */
    public static function allLocations(): array
    {
        return array_values(array_filter(
            self::staff(),
            fn (self $role): bool => ! in_array($role, self::locationScoped(), true),
        ));
    }

    /**
     * Roles whose permissions an admin may change on the roles and
     * permissions page. Admin always holds everything, so no one can lock
     * themselves out; driver only ever opens the driver account.
     *
     * @return list<self>
     */
    public static function editable(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $role): bool => $role->isEditable(),
        ));
    }

    /**
     * What an editable role may be granted: every permission but the driver
     * account, which is for drivers (and admins) only, and the Sentry-backed
     * errors and deployments pages, which are for admins only.
     *
     * @return list<Permission>
     */
    public static function assignablePermissions(): array
    {
        return array_values(array_filter(
            Permission::cases(),
            fn (Permission $permission): bool => ! in_array($permission, [
                Permission::AccessDriverAccount,
                Permission::ViewErrors,
                Permission::ViewDeployments,
            ], true),
        ));
    }

    public function isEditable(): bool
    {
        return ! in_array($this, [self::Admin, self::Driver], true);
    }

    /**
     * The permissions this role starts with, as granted by the
     * create_default_permissions migration. Admins change the live grants on
     * the roles and permissions page, so access is always checked against
     * the database, never against this list.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        $staffBasics = [
            Permission::ViewOverview,
            Permission::ViewLiveQueue,
            Permission::ViewCheckIns,
            Permission::ViewAppointments,
            Permission::ViewLocations,
            Permission::ViewNotifications,
            Permission::SearchDashboard,
            Permission::FilterCheckIns,
            Permission::SyncCheckInsToErp,
            Permission::WriteCheckInNotes,
            Permission::WriteAppointmentNotes,
            Permission::SaveCheckInColumnDisplay,
            Permission::SaveCheckInColumnOrder,
            Permission::FilterAppointments,
            Permission::SaveAppointmentColumnDisplay,
            Permission::SaveAppointmentColumnOrder,
            Permission::ViewCheckInPdf,
            Permission::PrintCheckInPdf,
            Permission::ViewAppointmentPdf,
            Permission::PrintAppointmentPdf,
        ];

        $shipping = [
            ...$staffBasics,
            Permission::CompleteCheckIns,
            Permission::EmailCheckInPdf,
            Permission::EmailAppointmentPdf,
            Permission::CancelCheckIns,
            Permission::CancelAppointments,
            Permission::RevealDriverLicenses,
            Permission::ArchiveCheckIns,
            Permission::RestoreCheckIns,
            Permission::ArchiveAppointments,
            Permission::RestoreAppointments,
            Permission::ExportCheckInsView,
            Permission::ExportAppointmentsView,
        ];

        return match ($this) {
            self::Admin => Permission::cases(),
            self::ShippingManager => [
                ...$shipping,
                Permission::ReopenCheckIns,
                Permission::UnsyncCheckInsFromErp,
                Permission::EditCheckIns,
                Permission::LinkCheckInAppointments,
                Permission::ReinstateCheckIns,
                Permission::ReinstateAppointments,
                Permission::PauseCheckIns,
                Permission::EditLocationHours,
                Permission::ViewAnalytics,
            ],
            self::Shipping => $shipping,
            self::Sales => [...$staffBasics, Permission::ViewAllLocations, Permission::ViewAnalytics],
            self::Driver => [Permission::AccessDriverAccount],
        };
    }
}
