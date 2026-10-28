import { Link } from '@inertiajs/react';
import { ChevronRight, MapPin } from 'lucide-react';

import StatusBadge from '@/components/StatusBadge';
import type { AppointmentRowData } from './types';


export default function AppointmentRow({
    row,
    referenceLabel,
    statusLabel,
}: {
    row: AppointmentRowData;
    referenceLabel: string;
    statusLabel: string;
}) {
    return (
        <li>
            <Link
                href={row.href}
                className="flex items-center gap-3.5 px-4 py-3.5 transition-colors hover:bg-gray-50/60 dark:hover:bg-gray-800/40"
            >
                {/* Same date chip as the next-appointment card on the account hub. */}
                <span className="flex w-11 shrink-0 flex-col items-center justify-center rounded-xl bg-brand-green/10 py-1.5 text-brand-green">
                    <span className="text-[10px] font-bold tracking-wide uppercase">
                        {row.monthShort}
                    </span>
                    <span className="text-base leading-none font-bold">
                        {row.day}
                    </span>
                </span>

                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-semibold text-brand-grey dark:text-gray-100">
                        {row.time}
                    </p>
                    <p className="mt-0.5 flex items-center gap-1 truncate text-xs text-gray-400 dark:text-gray-500">
                        <MapPin className="h-3 w-3 shrink-0" />
                        <span className="truncate">{row.locationName}</span>
                    </p>
                    <p className="mt-0.5 truncate text-[11px] text-gray-400 dark:text-gray-500">
                        {referenceLabel} #{row.referenceNumber}
                    </p>
                </div>

                <StatusBadge status={row.status} label={statusLabel} />
                <ChevronRight className="h-4 w-4 shrink-0 text-gray-300 dark:text-gray-600" />
            </Link>
        </li>
    );
}
