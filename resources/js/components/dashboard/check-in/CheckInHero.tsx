import { formatDistanceStrict } from 'date-fns';
import {
    CalendarClock,
    Clock,
    MapPin,
    Package,
    StickyNote,
    Truck,
    UserRound,
} from 'lucide-react';

import CheckInProgress from '@/components/dashboard/check-in/CheckInProgress';
import CheckInToolbar from '@/components/dashboard/check-in/CheckInToolbar';
import StatChip from '@/components/dashboard/StatChip';
import type {
    CheckInFormFields,
    CheckInRecord,
} from '@/components/dashboard/types';
import StatusBadge from '@/components/StatusBadge';
import {
    formatDateTime,
    formatLabel,
    formatTimeAgo,
} from '@/lib/dashboard/format';

/** How long the driver has been (or was) on site: until completion, or until now while pending. */
function timeOnSite(checkIn: CheckInRecord): string | null {
    if (checkIn.status === 'cancelled') {
        return null;
    }

    const end =
        checkIn.completedAt !== null
            ? new Date(checkIn.completedAt)
            : new Date();

    return formatDistanceStrict(new Date(checkIn.createdAt), end);
}

/** The top of the check-in detail page: identity, status, lifecycle, key facts and actions. */
export default function CheckInHero({
    checkIn,
    fields,
    truckColors,
    noteCount = 0,
}: {
    checkIn: CheckInRecord;
    /** The location's optional fields, for the toolbar's edit form. */
    fields: CheckInFormFields;
    /** The truck color slugs the edit form offers. */
    truckColors: string[];
    /** Staff notes on this check-in; the chip shows only when there are some. */
    noteCount?: number;
}) {
    const onSite = timeOnSite(checkIn);
    const poCount = checkIn.purchaseOrders.length;

    return (
        <section className="mb-6 rounded-xl bg-white shadow-xs dark:bg-gray-800">
            <div className="flex flex-col gap-5 p-5 lg:flex-row lg:items-start lg:justify-between">
                <div className="flex min-w-0 items-start gap-4">
                    <span className="flex size-12 shrink-0 items-center justify-center rounded-xl bg-brand-green/12 text-brand-green">
                        <Truck className="size-6" />
                    </span>
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="font-mono text-2xl font-bold tracking-wide text-gray-800 md:text-3xl dark:text-gray-100">
                                {checkIn.referenceNumber}
                            </h1>
                            {checkIn.archivedAt !== null && (
                                <StatusBadge
                                    status="archived"
                                    label="Archived"
                                />
                            )}
                            <StatusBadge
                                status={checkIn.status}
                                label={formatLabel(checkIn.status)}
                            />
                            <StatusBadge
                                status={checkIn.erpStatus}
                                label={`ERP ${formatLabel(checkIn.erpStatus)}`}
                            />
                        </div>
                        <p className="mt-1 flex flex-wrap items-center gap-x-1.5 text-sm text-gray-500 dark:text-gray-400">
                            <MapPin className="size-3.5" />
                            <span className="font-medium text-gray-600 dark:text-gray-300">
                                {checkIn.location.name}
                            </span>
                            <span aria-hidden="true">·</span>
                            <span>
                                Submitted {formatDateTime(checkIn.createdAt)}
                            </span>
                            <span aria-hidden="true">·</span>
                            <span>{formatTimeAgo(checkIn.createdAt)}</span>
                        </p>
                    </div>
                </div>

                <CheckInToolbar
                    checkIn={checkIn}
                    fields={fields}
                    truckColors={truckColors}
                />
            </div>

            <div className="border-t border-gray-100 px-5 py-5 dark:border-gray-700/60">
                <CheckInProgress checkIn={checkIn} />
                {checkIn.status === 'cancelled' &&
                    checkIn.cancelledReason !== null && (
                        <p className="mt-4 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-600 dark:bg-gray-900/40 dark:text-gray-300">
                            <span className="font-semibold text-gray-800 dark:text-gray-100">
                                Cancellation reason:
                            </span>{' '}
                            <span className="whitespace-pre-line">
                                {checkIn.cancelledReason}
                            </span>
                        </p>
                    )}
            </div>

            <div className="flex flex-wrap gap-2 border-t border-gray-100 px-5 py-4 dark:border-gray-700/60">
                {onSite !== null && (
                    <StatChip
                        icon={Clock}
                        label={
                            checkIn.completedAt !== null
                                ? 'Time on site'
                                : 'On site for'
                        }
                        value={onSite}
                    />
                )}
                <a
                    href="#purchase-orders"
                    className="rounded-lg focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:outline-none"
                >
                    <StatChip
                        icon={Package}
                        label="Purchase orders"
                        value={String(poCount)}
                    />
                </a>
                <StatChip
                    icon={CalendarClock}
                    label="Appointment"
                    value={checkIn.appointmentReference ?? 'Walk-in'}
                />
                <StatChip
                    icon={UserRound}
                    label="Driver"
                    value={checkIn.driver.hasAccount ? 'Registered' : 'Guest'}
                />
                {noteCount > 0 && (
                    <a
                        href="#notes"
                        className="rounded-lg focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:outline-none"
                    >
                        <StatChip
                            icon={StickyNote}
                            label="Notes"
                            value={String(noteCount)}
                        />
                    </a>
                )}
            </div>
        </section>
    );
}
