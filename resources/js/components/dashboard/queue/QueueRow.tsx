import { Link } from '@inertiajs/react';

import type { QueueItemProps } from '@/components/dashboard/queue/QueueCard';
import {
    enterLeaveClass,
    NewTag,
} from '@/components/dashboard/queue/QueueCard';
import { formatWait, waitTone } from '@/components/dashboard/queue/wait';
import RegionLabel from '@/components/dashboard/RegionLabel';
import dashboardRoutes from '@/routes/dashboard';

/**
 * A pending check-in as one dense line, so a busy day fits on one screen. Secondary columns drop
 * away as the screen narrows; the reference, driver and timer always stay.
 */
export default function QueueRow({
    checkIn,
    now,
    isFresh,
    isLeaving,
}: QueueItemProps) {
    const waited = now - new Date(checkIn.createdAt).getTime();
    const tone = waitTone(waited);

    return (
        <li className={enterLeaveClass(isLeaving)}>
            <Link
                href={dashboardRoutes.checkIns.show.url(checkIn.uuid)}
                className={`grid grid-cols-[1fr_auto] items-center gap-x-4 border-l-4 px-4 py-2.5 text-sm transition hover:bg-gray-50 md:grid-cols-[8rem_1fr_1fr_auto] xl:grid-cols-[8rem_1fr_1fr_8rem_10rem_auto] dark:hover:bg-gray-700/40 ${tone.border} ${
                    isFresh ? 'bg-brand-green/5' : ''
                }`}
            >
                <span className="flex min-w-0 items-center gap-2 md:contents">
                    <span className="font-mono font-semibold text-gray-800 dark:text-gray-100">
                        {checkIn.referenceNumber}
                    </span>
                    <span className="flex min-w-0 items-center gap-2 truncate font-medium text-gray-800 dark:text-gray-100">
                        <span className="truncate">{checkIn.driver.name}</span>
                        {isFresh && <NewTag />}
                    </span>
                </span>
                <span className="hidden truncate text-gray-500 md:block dark:text-gray-400">
                    {checkIn.customer}
                </span>
                <span className="hidden min-w-0 items-center gap-2 font-mono text-xs text-gray-600 xl:flex dark:text-gray-300">
                    <span className="shrink-0">{checkIn.truck.plate}</span>
                    <RegionLabel
                        state={checkIn.truck.plateState}
                        country={checkIn.truck.plateCountry}
                        className="font-sans text-gray-400"
                    />
                </span>
                <span className="hidden truncate text-xs text-gray-500 xl:block dark:text-gray-400">
                    {checkIn.location.name}
                </span>
                <span
                    className={`text-right font-mono font-semibold tabular-nums ${tone.text}`}
                    title="Waiting since check-in"
                >
                    {formatWait(waited)}
                </span>
            </Link>
        </li>
    );
}
