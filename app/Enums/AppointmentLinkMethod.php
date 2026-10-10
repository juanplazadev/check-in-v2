<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a check-in came to be tied to its appointment: the driver converted
 * the appointment directly, or a staff member linked a separately created
 * check-in afterwards.
 *
 * Distinct from claimed_via, which records a guest record being attached to
 * a driver account after registration.
 */
enum AppointmentLinkMethod: string
{
    case Conversion = 'conversion';
    case Staff = 'staff';
}
