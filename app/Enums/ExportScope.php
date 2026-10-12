<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The two spreadsheets of a dashboard table: what the table shows (its
 * filters, sort and visible columns), or every record the user may see with
 * every column. Mirrored as ExportScope in DataTableExport.tsx.
 */
enum ExportScope: string
{
    case View = 'view';
    case All = 'all';
}
