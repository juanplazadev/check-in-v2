<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The two check-in spreadsheets: what the table shows (its filters, sort and
 * visible columns), or every check-in the user may see with every column.
 * Mirrored as ExportScope in DataTableExport.tsx.
 */
enum CheckInExportScope: string
{
    case View = 'view';
    case All = 'all';
}
