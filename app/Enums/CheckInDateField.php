<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Which timestamp the dashboard's date range filter applies to. The column
 * comes from this allowlist, never from the request.
 */
enum CheckInDateField: string
{
    case Submitted = 'submitted';
    case Completed = 'completed';
    case ErpSynced = 'erp_synced';

    /**
     * A literal, so the dashboard can place it in a raw order clause.
     *
     * @return literal-string
     */
    public function column(): string
    {
        return match ($this) {
            self::Submitted => 'created_at',
            self::Completed => 'completed_at',
            self::ErpSynced => 'erp_synced_at',
        };
    }
}
