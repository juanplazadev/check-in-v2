import { Head, router } from '@inertiajs/react';
import { CheckCheck } from 'lucide-react';
import { useEffect, useState } from 'react';

import AssignDoorDialog from '@/components/dashboard/check-in/AssignDoorDialog';
import { useLiveAlerts } from '@/components/dashboard/live/LiveAlerts';
import LiveIndicator from '@/components/dashboard/live/LiveIndicator';
import PageHeader from '@/components/dashboard/PageHeader';
import ArrivingRail from '@/components/dashboard/queue/ArrivingRail';
import QueueCard from '@/components/dashboard/queue/QueueCard';
import QueueRow from '@/components/dashboard/queue/QueueRow';
import { useLiveRecords } from '@/components/dashboard/queue/useLiveRecords';
import type {
    AppointmentRecord,
    CheckInDoorOption,
    CheckInRecord,
    LocationSummary,
} from '@/components/dashboard/types';
import { useAppointmentFeed } from '@/hooks/useAppointmentFeed';
import { useCan } from '@/hooks/useCan';
import { useCheckInFeed } from '@/hooks/useCheckInFeed';
import DashboardLayout from '@/layouts/DashboardLayout';

interface QueueProps {
    checkIns: CheckInRecord[];
    windowHours: number;
    appointments: AppointmentRecord[];
    appointmentWindow: { lateGraceHours: number; aheadHours: number };
    locations: LocationSummary[];
    /** Optional prop: active doors by location uuid, loaded when a door dialog opens. */
    doorOptions?: Record<string, CheckInDoorOption[]>;
}

type SortOrder = 'oldest' | 'newest';
type Density = 'cards' | 'compact';

const LOCATION_KEY = 'dashboard.queue.location';
const SORT_KEY = 'dashboard.queue.sort';
const DENSITY_KEY = 'dashboard.queue.density';

/** Check-ins rendered before "Show more", per density. */
const COLLAPSED_COUNT: Record<Density, number> = { cards: 12, compact: 30 };

function readStored(key: string): string | null {
    try {
        return window.localStorage.getItem(key);
    } catch {
        return null;
    }
}

function writeStored(key: string, value: string): void {
    try {
        window.localStorage.setItem(key, value);
    } catch {
        // Blocked storage: the choice lasts for this visit only.
    }
}

/** One clock for every card's timer, rather than an interval per card. */
function useNow(intervalMs: number): number {
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const id = window.setInterval(() => setNow(Date.now()), intervalMs);

        return () => window.clearInterval(id);
    }, [intervalMs]);

    return now;
}

function SegmentedControl<T extends string>({
    label,
    value,
    options,
    onChange,
}: {
    label: string;
    value: T;
    options: Record<T, string>;
    onChange: (value: T) => void;
}) {
    return (
        <div
            className="inline-flex rounded-lg bg-white p-1 shadow-sm dark:bg-gray-800"
            role="group"
            aria-label={label}
        >
            {(Object.keys(options) as T[]).map((option) => (
                <button
                    key={option}
                    type="button"
                    aria-pressed={value === option}
                    onClick={() => onChange(option)}
                    className={`rounded-md px-3 py-1 text-sm font-medium transition ${
                        value === option
                            ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-800'
                            : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'
                    }`}
                >
                    {options[option]}
                </button>
            ))}
        </div>
    );
}

/**
 * The screen shipping leaves open on the dock: pending check-ins, with the appointments about to
 * arrive in a rail beside them, both kept current over the websocket. Props are only the starting
 * state and the catch-up after a dropped connection; the feeds add, update and remove from there.
 */
export default function QueueIndex({
    checkIns,
    windowHours,
    appointments,
    appointmentWindow,
    locations,
    doorOptions,
}: QueueProps) {
    const can = useCan();
    const [doorFor, setDoorFor] = useState<CheckInRecord | null>(null);
    const { status } = useLiveAlerts();
    const now = useNow(1000);

    const queue = useLiveRecords(checkIns);
    const arriving = useLiveRecords(appointments);
    const [location, setLocation] = useState('all');
    const [sort, setSort] = useState<SortOrder>('oldest');
    const [density, setDensity] = useState<Density>('cards');
    const [expanded, setExpanded] = useState(false);

    useEffect(() => {
        // eslint-disable-next-line react-hooks/set-state-in-effect
        setLocation(readStored(LOCATION_KEY) ?? 'all');
        setSort(readStored(SORT_KEY) === 'newest' ? 'newest' : 'oldest');
        setDensity(readStored(DENSITY_KEY) === 'compact' ? 'compact' : 'cards');
    }, []);

    const reload = () => router.reload({ only: ['checkIns', 'appointments'] });

    useCheckInFeed({
        onEvent: (event) =>
            event.type === 'deleted'
                ? queue.remove(event.uuid)
                : queue.apply(
                      event.type,
                      event.checkIn,
                      event.checkIn.status === 'pending',
                  ),
        onReconnect: reload,
    });

    useAppointmentFeed({
        onEvent: ({ type, appointment }) =>
            arriving.apply(
                type,
                appointment,
                appointment.status === 'scheduled',
            ),
        onReconnect: reload,
    });

    const atLocation = <T extends { location: LocationSummary }>(
        records: T[],
    ): T[] =>
        location === 'all'
            ? records
            : records.filter(
                  (record) => String(record.location.id) === location,
              );

    const visible = [...atLocation(queue.records)].sort((a, b) => {
        const difference =
            new Date(a.createdAt).getTime() - new Date(b.createdAt).getTime();

        return sort === 'oldest' ? difference : -difference;
    });

    const visibleArriving = [...atLocation(arriving.records)].sort(
        (a, b) =>
            new Date(a.scheduledFor).getTime() -
            new Date(b.scheduledFor).getTime(),
    );

    const limit = COLLAPSED_COUNT[density];
    const shown = expanded ? visible : visible.slice(0, limit);
    const hiddenCount = visible.length - shown.length;

    const changeLocation = (value: string) => {
        setLocation(value);
        setExpanded(false);
        writeStored(LOCATION_KEY, value);
    };

    const changeSort = (value: SortOrder) => {
        setSort(value);
        writeStored(SORT_KEY, value);
    };

    const changeDensity = (value: Density) => {
        setDensity(value);
        writeStored(DENSITY_KEY, value);
    };

    const QueueItem = density === 'cards' ? QueueCard : QueueRow;

    return (
        <>
            {/* The layout prefixes an unseen-arrivals count while the tab is in the background. */}
            <Head title="Live queue" />

            <PageHeader
                title="Live queue"
                subtitle={`Pending check-ins from the last ${windowHours} hours · appointments due in the next ${appointmentWindow.aheadHours}`}
                actions={
                    // One wrapping row, so the controls stack on a phone instead of overflowing.
                    <div className="flex flex-wrap items-center gap-2">
                        <LiveIndicator status={status} />
                        <label className="sr-only" htmlFor="queue-location">
                            Location
                        </label>
                        <select
                            id="queue-location"
                            className="form-select bg-white dark:bg-gray-800"
                            value={location}
                            onChange={(event) =>
                                changeLocation(event.target.value)
                            }
                        >
                            <option value="all">All locations</option>
                            {locations.map((option) => (
                                <option
                                    key={option.id}
                                    value={String(option.id)}
                                >
                                    {option.name}
                                </option>
                            ))}
                        </select>
                        <SegmentedControl
                            label="Order"
                            value={sort}
                            options={{
                                oldest: 'Oldest first',
                                newest: 'Newest first',
                            }}
                            onChange={changeSort}
                        />
                        <SegmentedControl
                            label="Density"
                            value={density}
                            options={{ cards: 'Cards', compact: 'Compact' }}
                            onChange={changeDensity}
                        />
                    </div>
                }
            />

            {status === 'offline' && (
                <div className="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300">
                    Not receiving live updates. This screen will catch up on its
                    own once the connection is back; reload the page if it stays
                    offline.
                </div>
            )}

            <div className="grid grid-cols-1 items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <div className="min-w-0">
                    {visible.length === 0 ? (
                        <div className="flex flex-col items-center rounded-xl bg-white px-6 py-16 text-center shadow-sm dark:bg-gray-800">
                            <div className="mb-4 flex size-12 items-center justify-center rounded-full bg-brand-green/10 text-brand-green">
                                <CheckCheck className="size-6" />
                            </div>
                            <h2 className="text-lg font-semibold text-gray-800 dark:text-gray-100">
                                All caught up
                            </h2>
                            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                New check-ins will appear here as drivers submit
                                them.
                            </p>
                        </div>
                    ) : (
                        <>
                            <ul
                                className={
                                    density === 'cards'
                                        ? 'grid grid-cols-1 gap-6 sm:grid-cols-2 2xl:grid-cols-3'
                                        : 'divide-y divide-gray-100 overflow-hidden rounded-xl bg-white shadow-sm dark:divide-gray-700/60 dark:bg-gray-800'
                                }
                            >
                                {shown.map((checkIn) => (
                                    <QueueItem
                                        key={checkIn.uuid}
                                        checkIn={checkIn}
                                        now={now}
                                        isFresh={queue.fresh.has(checkIn.uuid)}
                                        isLeaving={queue.leaving.has(
                                            checkIn.uuid,
                                        )}
                                        onAssignDoor={
                                            can('assign check-in docks')
                                                ? setDoorFor
                                                : undefined
                                        }
                                    />
                                ))}
                            </ul>
                            {visible.length > limit && (
                                <div className="mt-6 flex justify-center">
                                    <button
                                        type="button"
                                        onClick={() => setExpanded(!expanded)}
                                        className="rounded-lg bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                                    >
                                        {expanded
                                            ? 'Show less'
                                            : `Show ${hiddenCount} more`}
                                    </button>
                                </div>
                            )}
                        </>
                    )}
                </div>

                <ArrivingRail
                    appointments={visibleArriving}
                    now={now}
                    aheadHours={appointmentWindow.aheadHours}
                    fresh={arriving.fresh}
                    leaving={arriving.leaving}
                />
            </div>

            <AssignDoorDialog
                open={doorFor !== null}
                onOpenChange={(open) => !open && setDoorFor(null)}
                checkIn={doorFor}
                options={
                    doorFor ? doorOptions?.[doorFor.location.uuid] : undefined
                }
                reloadProp="doorOptions"
            />
        </>
    );
}

QueueIndex.layout = DashboardLayout;
