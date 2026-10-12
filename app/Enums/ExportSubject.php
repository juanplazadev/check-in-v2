<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a dashboard spreadsheet export holds. Carried in the export mail and in
 * the bell's export notifications, which word themselves and link by it.
 * Mirrored as ExportSubject in NotificationsMenu.tsx.
 */
enum ExportSubject: string
{
    case CheckIns = 'check-ins';
    case Appointments = 'appointments';

    public function singular(): string
    {
        return match ($this) {
            self::CheckIns => 'check-in',
            self::Appointments => 'appointment',
        };
    }

    public function plural(): string
    {
        return $this->value;
    }

    /**
     * The table the export was taken from.
     */
    public function indexRoute(): string
    {
        return match ($this) {
            self::CheckIns => 'dashboard.checkIns',
            self::Appointments => 'dashboard.appointments',
        };
    }
}
