<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The sections the roles and permissions matrix groups permissions into, in
 * display order. For display only; access never depends on a group.
 */
enum PermissionGroup: string
{
    case Dashboard = 'dashboard';
    case CheckIns = 'check-ins';
    case Appointments = 'appointments';
    case Documents = 'documents';
    case ExportsAndColumns = 'exports-and-columns';
    case Locations = 'locations';
    case Administration = 'administration';
    case Driver = 'driver';

    public function label(): string
    {
        return match ($this) {
            self::Dashboard => 'Dashboard',
            self::CheckIns => 'Check-ins',
            self::Appointments => 'Appointments',
            self::Documents => 'Documents',
            self::ExportsAndColumns => 'Exports & columns',
            self::Locations => 'Locations',
            self::Administration => 'Administration',
            self::Driver => 'Driver',
        };
    }
}
