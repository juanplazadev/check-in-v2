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
     * location_user pivot): every staff role without view all locations.
     * For seeding and display only; access checks go through the permission.
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
     * Staff who see every location, whatever they are attached to.
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
     * The permissions this role grants. Granted by the
     * create_default_permissions migration; change both together.
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
            ],
            self::Shipping => $shipping,
            self::Sales => [...$staffBasics, Permission::ViewAllLocations],
            self::Driver => [Permission::AccessDriverAccount],
        };
    }
}
