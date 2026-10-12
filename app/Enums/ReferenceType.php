<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The leading character of a reference number, which is also what partitions
 * the keyspace between the two tables.
 *
 * Check-ins and appointments each carry their own unique index, and no index
 * can span both tables, so identical references were possible across them.
 * Giving each type its own first character makes that collision unrepresentable
 * rather than merely unlikely, and costs nothing at runtime.
 *
 * A reference is also read aloud to support, so the prefix doubles as a way to
 * tell the two apart without a lookup.
 */
enum ReferenceType: string
{
    case CheckIn = 'C';
    case Appointment = 'A';
}
