import { Link } from '@inertiajs/react';
import { ChevronRight, MapPin } from 'lucide-react';

import StatusBadge from '@/components/StatusBadge';
import type { CheckInRowData } from './types';


export default function CheckInRow({
    row,
    referenceLabel,
    statusLabel,
}: {
    row: CheckInRowData;
    referenceLabel: string;
    statusLabel: string;
}) {
    return (
        <li>
            <Link
                href={row.href}
                className="flex items-center gap-3.5 px-4 py-3.5 transition-colors hover:bg-gray-50/60 dark:hover:bg-gray-800/40"
            >
                <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-green/10 text-brand-green">
                    <MapPin className="h-4 w-4" />
                </span>

                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-semibold text-brand-grey dark:text-gray-100">
                        {row.locationName}
                    </p>
                    <p className="mt-0.5 truncate text-xs text-gray-400 dark:text-gray-500">
                        {row.date} · {referenceLabel} #{row.referenceNumber}
                    </p>
                </div>

                <StatusBadge status={row.status} label={statusLabel} />
                <ChevronRight className="h-4 w-4 shrink-0 text-gray-300 dark:text-gray-600" />
            </Link>
        </li>
    );
}
