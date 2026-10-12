<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Which timestamp the dashboard appointment table's date range filter applies
 * to. The column comes from this allowlist, never from the request.
 */
enum AppointmentDateField: string
{
    case Scheduled = 'scheduled';
    case Booked = 'booked';

    /**
     * A literal, so the dashboard can place it in a raw order clause.
     *
     * @return literal-string
     */
    public function column(): string
    {
        return match ($this) {
            self::Scheduled => 'scheduled_for',
            self::Booked => 'created_at',
        };
    }
}
