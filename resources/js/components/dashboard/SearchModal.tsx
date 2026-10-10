import { Link, useHttp } from '@inertiajs/react';
import { CalendarClock, MapPin, Search, Truck, User } from 'lucide-react';
import { Dialog } from 'radix-ui';
import { useEffect, useEffectEvent, useState } from 'react';

import {
    formatDateTime,
    formatLabel,
    formatPhone,
} from '@/lib/dashboard/format';
import dashboardRoutes from '@/routes/dashboard';

interface SearchModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

type MatchField =
    | 'purchaseOrder'
    | 'truckPlate'
    | 'trailerPlate'
    | 'email'
    | 'cellphone'
    | 'customer'
    | 'license'
    | 'uuid';

/** Which field a record matched on; null when it was the reference or name, which the row already shows. */
type SearchMatch = { field: MatchField; value: string } | null;

/** Mirrors App\Queries\DashboardSearch::execute(). */
interface SearchResponse {
    checkIns: {
        uuid: string;
        referenceNumber: string;
        driverName: string;
        location: string;
        status: string;
        match: SearchMatch;
    }[];
    appointments: {
        uuid: string;
        referenceNumber: string;
        driverName: string;
        location: string;
        status: string;
        scheduledFor: string;
        match: SearchMatch;
    }[];
    users: { uuid: string; name: string; email: string }[];
    /** Admins only, and only for a full-UUID term. */
    locations: { uuid: string; name: string; abbreviation: string }[];
}

interface SearchResult {
    key: string;
    label: string;
    detail: string;
    /** Null for records with no dashboard page yet; the row then renders unlinked. */
    href: string | null;
    icon: typeof Truck;
    match: SearchMatch;
}

interface SearchGroup {
    heading: string;
    results: SearchResult[];
}

const MATCH_LABELS: Record<MatchField, string> = {
    purchaseOrder: 'PO',
    truckPlate: 'Truck plate',
    trailerPlate: 'Trailer plate',
    email: 'Email',
    cellphone: 'Phone',
    customer: 'Customer',
    license: 'License',
    uuid: 'UUID',
};

const MIN_LENGTH = 2;
const DEBOUNCE_MS = 250;

function groupsFrom(response: SearchResponse): SearchGroup[] {
    return [
        {
            heading: 'Check-ins',
            results: response.checkIns.map((checkIn) => ({
                key: `check-in-${checkIn.uuid}`,
                label: checkIn.referenceNumber,
                detail: `${checkIn.driverName} · ${checkIn.location} · ${formatLabel(checkIn.status)}`,
                href: dashboardRoutes.checkIns.show.url(checkIn.uuid),
                icon: Truck,
                match: checkIn.match,
            })),
        },
        {
            heading: 'Appointments',
            results: response.appointments.map((appointment) => ({
                key: `appointment-${appointment.uuid}`,
                label: appointment.referenceNumber,
                detail: `${appointment.driverName} · ${appointment.location} · ${formatDateTime(appointment.scheduledFor)}`,
                href: dashboardRoutes.appointments.show.url(appointment.uuid),
                icon: CalendarClock,
                match: appointment.match,
            })),
        },
        {
            heading: 'Users',
            results: response.users.map((user) => ({
                key: `user-${user.uuid}`,
                label: user.name,
                detail: user.email,
                href: dashboardRoutes.users.show.url(user.uuid),
                icon: User,
                match: null,
            })),
        },
        {
            heading: 'Locations',
            results: response.locations.map((location) => ({
                key: `location-${location.uuid}`,
                label: location.name,
                detail: location.abbreviation,
                href: null,
                icon: MapPin,
                match: null,
            })),
        },
    ].filter((group) => group.results.length > 0);
}

/**
 * The matched value as the user should read it. The server only ever sends
 * a license's last four characters.
 */
function displayValue(match: NonNullable<SearchMatch>): string {
    if (match.field === 'cellphone') {
        return formatPhone(match.value);
    }

    return match.field === 'license' ? `•••• ${match.value}` : match.value;
}

/** Bolds the first case-insensitive occurrence of the term in the value. */
function Highlighted({ value, term }: { value: string; term: string }) {
    const start = value.toLowerCase().indexOf(term.toLowerCase());

    if (start === -1) {
        return <span className="font-medium">{value}</span>;
    }

    const end = start + term.length;

    return (
        <span>
            {value.slice(0, start)}
            <mark className="bg-transparent font-semibold text-gray-800 dark:text-gray-100">
                {value.slice(start, end)}
            </mark>
            {value.slice(end)}
        </span>
    );
}

const ROW_CLASS =
    'group flex items-center rounded-lg p-2 text-gray-800 dark:text-gray-100';

/** A result's icon, label, detail and matched field, linked or not. */
function ResultRow({ result, term }: { result: SearchResult; term: string }) {
    const Icon = result.icon;

    return (
        <>
            <Icon className="mt-0.5 mr-3 size-4 shrink-0 self-start text-gray-400 dark:text-gray-500" />
            <div className="min-w-0">
                <div className="flex">
                    <span className="font-medium">{result.label}</span>
                    <span className="ml-2 truncate text-gray-500 dark:text-gray-400">
                        {result.detail}
                    </span>
                </div>
                {result.match && (
                    <div className="truncate text-xs text-gray-500 dark:text-gray-400">
                        {MATCH_LABELS[result.match.field]}:{' '}
                        <Highlighted
                            value={displayValue(result.match)}
                            term={term}
                        />
                    </div>
                )}
            </div>
        </>
    );
}

/**
 * Mosaic's ModalSearch on a Radix dialog, backed by the dashboard search
 * endpoint. Scoping to the user's locations, and users for admins only, happen
 * on the server.
 */
export default function SearchModal({ open, onOpenChange }: SearchModalProps) {
    const [query, setQuery] = useState('');
    const [settled, setSettled] = useState<{
        term: string;
        groups: SearchGroup[];
    } | null>(null);
    const { get, cancel } = useHttp<Record<string, never>, SearchResponse>({});
    const needle = query.trim();
    const searchable = needle.length >= MIN_LENGTH;

    // A response for an older term is ignored, so a slow request that lands
    // after a newer one never overwrites it.
    const groups = settled?.term === needle ? settled.groups : null;

    const search = useEffectEvent((term: string) => {
        cancel();
        get(dashboardRoutes.search.url({ query: { q: term } }))
            .then((response) =>
                setSettled({ term, groups: groupsFrom(response) }),
            )
            .catch(() => {
                // Cancelled by a newer keystroke, or failed; the row keeps loading
                // until the next term rather than showing a misleading empty state.
            });
    });

    useEffect(() => {
        if (!searchable) {
            return;
        }

        const timer = setTimeout(() => search(needle), DEBOUNCE_MS);

        return () => clearTimeout(timer);
    }, [needle, searchable]);

    return (
        <Dialog.Root
            open={open}
            onOpenChange={(next) => {
                onOpenChange(next);

                if (!next) {
                    cancel();
                    setQuery('');
                }
            }}
        >
            <Dialog.Portal>
                <Dialog.Overlay className="fixed inset-0 z-50 bg-gray-900/30 data-[state=open]:animate-in data-[state=open]:fade-in-0" />
                <Dialog.Content className="fixed inset-x-4 top-20 z-50 mx-auto max-h-[calc(100dvh-10rem)] max-w-2xl overflow-auto rounded-lg border border-transparent bg-white shadow-lg data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:slide-in-from-top-4 dark:border-gray-700/60 dark:bg-gray-800">
                    <Dialog.Title className="sr-only">Search</Dialog.Title>
                    <Dialog.Description className="sr-only">
                        Search check-ins, appointments and users by reference,
                        name, phone, email, PO, plate, license or UUID.
                    </Dialog.Description>

                    <form
                        className="border-b border-gray-200 dark:border-gray-700/60"
                        onSubmit={(event) => event.preventDefault()}
                    >
                        <div className="relative">
                            <label
                                htmlFor="dashboard-search"
                                className="sr-only"
                            >
                                Search
                            </label>
                            <input
                                id="dashboard-search"
                                className="w-full appearance-none border-0 bg-white py-3 pr-4 pl-10 text-gray-800 placeholder-gray-400 focus:ring-transparent focus:outline-none dark:bg-gray-800 dark:text-gray-100 dark:placeholder-gray-500"
                                type="search"
                                placeholder="Reference, name, phone, email, PO, plate, license or UUID…"
                                value={query}
                                onChange={(event) =>
                                    setQuery(event.target.value)
                                }
                                autoComplete="off"
                            />
                            <Search className="absolute inset-y-0 left-0 my-auto ml-4 size-4 text-gray-400 dark:text-gray-500" />
                        </div>
                    </form>

                    <div className="px-2 py-4">
                        {!searchable && (
                            <div className="px-2 text-xs font-semibold text-gray-400 uppercase dark:text-gray-500">
                                Type to search
                            </div>
                        )}
                        {searchable && groups === null && (
                            <div
                                className="animate-pulse space-y-3 px-2 py-1"
                                aria-label="Searching"
                            >
                                <div className="h-3 w-24 rounded bg-gray-200 dark:bg-gray-700" />
                                <div className="h-4 w-3/4 rounded bg-gray-200 dark:bg-gray-700" />
                                <div className="h-4 w-1/2 rounded bg-gray-200 dark:bg-gray-700" />
                            </div>
                        )}
                        {groups?.length === 0 && (
                            <p className="px-2 text-sm text-gray-500 dark:text-gray-400">
                                Nothing matches “{needle}”.
                            </p>
                        )}
                        {groups?.map((group) => (
                            <div key={group.heading} className="mb-3 last:mb-0">
                                <div className="mb-2 px-2 text-xs font-semibold text-gray-400 uppercase dark:text-gray-500">
                                    {group.heading}
                                </div>
                                <ul className="text-sm">
                                    {group.results.map((result) => (
                                        <li key={result.key}>
                                            {result.href === null ? (
                                                <div className={ROW_CLASS}>
                                                    <ResultRow
                                                        result={result}
                                                        term={needle}
                                                    />
                                                </div>
                                            ) : (
                                                <Link
                                                    href={result.href}
                                                    onClick={() =>
                                                        onOpenChange(false)
                                                    }
                                                    className={`${ROW_CLASS} hover:bg-gray-100 dark:hover:bg-gray-700/20`}
                                                >
                                                    <ResultRow
                                                        result={result}
                                                        term={needle}
                                                    />
                                                </Link>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        ))}
                    </div>
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}
