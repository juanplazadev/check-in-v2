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
     * location_user pivot). Every other staff role sees every location.
     *
     * @return list<self>
     */
    public static function locationScoped(): array
    {
        return [self::ShippingManager, self::Shipping];
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
     * Who may use the driver account area: drivers, plus admins.
     *
     * @return list<self>
     */
    public static function accountHolders(): array
    {
        return [self::Driver, self::Admin];
    }
}
