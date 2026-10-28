import { Link } from '@inertiajs/react';
import { Clock, Package, Warehouse } from 'lucide-react';

import { formatWait, waitTone } from '@/components/dashboard/queue/wait';
import RegionLabel from '@/components/dashboard/RegionLabel';
import type { CheckInRecord } from '@/components/dashboard/types';
import dashboardRoutes from '@/routes/dashboard';

interface QueueItemProps {
    checkIn: CheckInRecord;
    now: number;
    isFresh: boolean;
    isLeaving: boolean;
    /** Absent when the viewer may not assign doors. */
    onAssignDoor?: (checkIn: CheckInRecord) => void;
}

/** The truck's door, or a muted "No door" when none is assigned yet. */
export function DoorBadge({ checkIn }: { checkIn: CheckInRecord }) {
    return checkIn.dock ? (
        <span className="inline-flex min-w-0 items-center gap-1 rounded-md bg-sky-50 px-2 py-0.5 text-xs font-semibold text-sky-700 dark:bg-sky-500/15 dark:text-sky-300">
            <Warehouse className="size-3.5 shrink-0" aria-hidden />
            <span className="truncate">{checkIn.dock.label}</span>
        </span>
    ) : (
        <span className="text-xs text-gray-400 dark:text-gray-500">
            No door
        </span>
    );
}

export const ASSIGN_DOOR_BUTTON =
    'btn-sm shrink-0 border-gray-200 text-gray-700 hover:border-gray-300 dark:border-gray-700/60 dark:text-gray-200 dark:hover:border-gray-600';

export function NewTag() {
    return (
        <span className="rounded-full bg-brand-green px-2 py-0.5 text-[10px] font-bold tracking-wide text-white uppercase">
            New
        </span>
    );
}

export function enterLeaveClass(isLeaving: boolean): string {
    return `transition-all duration-400 ${
        isLeaving ? 'scale-95 opacity-0' : 'animate-in zoom-in-95 fade-in'
    }`;
}

/** A pending check-in as a full card, for the roomy layout. */
export default function QueueCard({
    checkIn,
    now,
    isFresh,
    isLeaving,
    onAssignDoor,
}: QueueItemProps) {
    const waited = now - new Date(checkIn.createdAt).getTime();
    const tone = waitTone(waited);

    return (
        <li
            className={`${enterLeaveClass(isLeaving)} flex h-full flex-col rounded-xl border-l-4 bg-white shadow-sm transition hover:shadow-md dark:bg-gray-800 ${tone.border} ${
                isFresh ? 'ring-2 ring-brand-green/60' : ''
            }`}
        >
            <Link
                href={dashboardRoutes.checkIns.show.url(checkIn.uuid)}
                className="block flex-1 p-5 pb-3"
            >
                <div className="flex items-start justify-between gap-3">
                    <div className="flex items-center gap-2">
                        <span className="font-mono text-sm font-semibold text-gray-800 dark:text-gray-100">
                            {checkIn.referenceNumber}
                        </span>
                        {isFresh && <NewTag />}
                    </div>
                    <span
                        className={`inline-flex items-center gap-1 font-mono text-sm font-semibold tabular-nums ${tone.text}`}
                        title="Waiting since check-in"
                    >
                        <Clock className="size-3.5" />
                        {formatWait(waited)}
                    </span>
                </div>

                <div className="mt-3 text-lg leading-tight font-semibold text-gray-800 dark:text-gray-100">
                    {checkIn.driver.name}
                </div>
                <div className="truncate text-sm text-gray-500 dark:text-gray-400">
                    {checkIn.customer}
                </div>

                <dl className="mt-4 grid grid-cols-2 gap-2 font-mono text-xs">
                    <div className="rounded-md bg-gray-50 px-2 py-1.5 dark:bg-gray-700/40">
                        <dt className="font-sans text-[10px] text-gray-400 uppercase">
                            Truck
                        </dt>
                        <dd className="font-semibold text-gray-700 dark:text-gray-200">
                            <div>{checkIn.truck.plate}</div>
                            <RegionLabel
                                state={checkIn.truck.plateState}
                                country={checkIn.truck.plateCountry}
                                className="font-sans text-[11px] font-normal text-gray-400"
                            />
                        </dd>
                    </div>
                    <div className="rounded-md bg-gray-50 px-2 py-1.5 dark:bg-gray-700/40">
                        <dt className="font-sans text-[10px] text-gray-400 uppercase">
                            Trailer
                        </dt>
                        <dd className="font-semibold text-gray-700 dark:text-gray-200">
                            <div>{checkIn.trailer.plate}</div>
                            <RegionLabel
                                state={checkIn.trailer.plateState}
                                country={checkIn.trailer.plateCountry}
                                className="font-sans text-[11px] font-normal text-gray-400"
                            />
                        </dd>
                    </div>
                </dl>

                <div className="mt-4 flex items-center justify-between gap-2 text-xs text-gray-500 dark:text-gray-400">
                    <span className="truncate">{checkIn.location.name}</span>
                    <span className="inline-flex shrink-0 items-center gap-1">
                        <Package className="size-3.5" />
                        {checkIn.purchaseOrders.length} PO
                        {checkIn.purchaseOrders.length === 1 ? '' : 's'}
                        {!checkIn.driver.hasAccount && (
                            <span className="ml-2 rounded-sm bg-gray-100 px-1.5 py-0.5 dark:bg-gray-700">
                                Guest
                            </span>
                        )}
                    </span>
                </div>
            </Link>
            {/* Outside the link: a button cannot sit inside an anchor. */}
            <div className="flex items-center justify-between gap-2 border-t border-gray-100 px-5 py-3 dark:border-gray-700/60">
                <DoorBadge checkIn={checkIn} />
                {onAssignDoor && (
                    <button
                        type="button"
                        onClick={() => onAssignDoor(checkIn)}
                        className={ASSIGN_DOOR_BUTTON}
                    >
                        {checkIn.dock ? 'Change door' : 'Assign door'}
                    </button>
                )}
            </div>
        </li>
    );
}

export type { QueueItemProps };
