<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The columns a staff member may show on the dashboard appointment table.
 * Mirrored in resources/js/pages/Dashboard/Appointments/Index.tsx.
 */
enum AppointmentColumn: string
{
    case Reference = 'reference';
    case Date = 'date';
    case Driver = 'driver';
    case Cellphone = 'cellphone';
    case Location = 'location';
    case PurchaseOrders = 'purchaseOrders';
    case Status = 'status';
    case CheckIn = 'checkIn';
    case Dock = 'dock';

    /**
     * The reference links to the record, so it is always shown.
     */
    public function isHideable(): bool
    {
        return $this !== self::Reference;
    }
}
