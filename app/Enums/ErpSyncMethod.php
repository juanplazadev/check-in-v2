<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a check-in reached the ERP: pushed by a staff member (shipping or
 * sales), or by the automatic sync.
 */
enum ErpSyncMethod: string
{
    case Manual = 'manual';
    case Automatic = 'automatic';
}
