/**
 * Placeholder data for the frontend-only pass of the staff dashboard. Generated from a seeded PRNG
 * against a fixed clock so server and client renders agree. Every export here is replaced by page
 * props once the backend lands, and the shapes in components/dashboard/types.ts are the contract.
 */
import type {
    AppointmentRecord,
    AppointmentStatus,
    CheckInErpStatus,
    CheckInRecord,
    CheckInStatus,
    DashboardUser,
    LocationSummary,
    RecordHistoryEntry,
    TrailerChute,
} from '@/components/dashboard/types';

export const MOCK_NOW = new Date('2026-10-04T16:00:00Z');

const HOUR = 60 * 60 * 1000;
const DAY = 24 * HOUR;

function createRandom(seed: number): () => number {
    let state = seed;

    return () => {
        state |= 0;
        state = (state + 0x6d2b79f5) | 0;
        let t = Math.imul(state ^ (state >>> 15), 1 | state);
        t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;

        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}

const random = createRandom(20261004);

function pick<T>(items: readonly T[]): T {
    return items[Math.floor(random() * items.length)];
}

function weighted<T extends string>(weights: Record<T, number>): T {
    const entries = Object.entries(weights) as [T, number][];
    const total = entries.reduce((sum, [, weight]) => sum + weight, 0);
    let roll = random() * total;

    for (const [value, weight] of entries) {
        roll -= weight;

        if (roll <= 0) {
            return value;
        }
    }

    return entries[0][0];
}

function between(min: number, max: number): number {
    return Math.floor(random() * (max - min + 1)) + min;
}

const HEX = '0123456789abcdef';
const REFERENCE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

function uuid(): string {
    const hex = Array.from({ length: 32 }, () => HEX[between(0, 15)]);
    hex[12] = '4';
    hex[16] = HEX[8 + between(0, 3)];
    const s = hex.join('');

    return `${s.slice(0, 8)}-${s.slice(8, 12)}-${s.slice(12, 16)}-${s.slice(16, 20)}-${s.slice(20)}`;
}

/** Same shape as App\Reference\ReferenceNumberGenerator: a type prefix plus 7 characters. */
function reference(prefix: 'C' | 'A'): string {
    return (
        prefix +
        Array.from({ length: 7 }, () => pick([...REFERENCE_ALPHABET])).join('')
    );
}

function plate(): string {
    return `${pick([...REFERENCE_ALPHABET])}${pick([...REFERENCE_ALPHABET])}${between(1000, 9999)}${pick([...REFERENCE_ALPHABET])}`;
}

function cellphone(): string {
    return `+1${between(200, 989)}${between(200, 999)}${between(1000, 9999)}`;
}

export const MOCK_LOCATIONS: LocationSummary[] = [
    { id: 1, name: 'Pompano Beach, FL - Martori Farms' },
    { id: 2, name: 'Eddystone, PA - Penn Terminals' },
    { id: 3, name: 'Oxnard, CA - Channel Islands Logistics' },
    { id: 4, name: 'Baytown, TX - Foremost Fresh Direct' },
    { id: 5, name: 'Firebaugh, CA' },
];

const FIRST_NAMES = [
    'Carlos',
    'Maria',
    'James',
    'Luis',
    'Ana',
    'Robert',
    'Jose',
    'Linda',
    'Miguel',
    'Patricia',
    'David',
    'Rosa',
    'Kevin',
    'Sofia',
    'Daniel',
    'Elena',
];
const LAST_NAMES = [
    'Garcia',
    'Johnson',
    'Martinez',
    'Smith',
    'Hernandez',
    'Lopez',
    'Williams',
    'Gonzalez',
    'Brown',
    'Perez',
    'Davis',
    'Ramirez',
    'Torres',
    'Flores',
];
const CUSTOMERS = [
    'Fresh Harvest Co.',
    'Sunrise Produce',
    'Green Valley Markets',
    'Pacific Fruit Dist.',
    'Lone Star Grocers',
    'Atlantic Foods',
    'Desert Bloom Farms',
];
const TRUCKS = [
    'Freightliner Cascadia',
    'Peterbilt 579',
    'Kenworth T680',
    'Volvo VNL',
    'International LT',
    'Mack Anthem',
];
const COLORS = ['White', 'Red', 'Blue', 'Black', 'Silver', 'Green'];
const STATES = [
    'Florida',
    'California',
    'Texas',
    'Pennsylvania',
    'Arizona',
    'Georgia',
    'New Jersey',
    'North Carolina',
];
const DESTINATIONS: [string, string][] = [
    ['Atlanta', 'GA'],
    ['Dallas', 'TX'],
    ['Newark', 'NJ'],
    ['Chicago', 'IL'],
    ['Phoenix', 'AZ'],
    ['Charlotte', 'NC'],
    ['Denver', 'CO'],
];
const STAFF_NAMES = ['Dock Office', 'Gate Clerk', 'Shift Lead'];

function driverName(): string {
    return `${pick(FIRST_NAMES)} ${pick(LAST_NAMES)}`;
}

function purchaseOrders(): string[] {
    return Array.from(
        { length: between(1, 3) },
        () => `PO-${between(100000, 999999)}`,
    );
}

function iso(timestamp: number): string {
    return new Date(timestamp).toISOString();
}

let historyId = 0;

function historyEntry(
    event: RecordHistoryEntry['event'],
    description: string,
    at: number,
    actor: string | null = null,
): RecordHistoryEntry {
    historyId += 1;

    return {
        id: historyId,
        event,
        description,
        changes: null,
        actor,
        createdAt: iso(at),
    };
}

function checkInHistory(
    createdAt: number,
    status: CheckInStatus,
    erpStatus: CheckInErpStatus,
): RecordHistoryEntry[] {
    const entries = [
        historyEntry('created', 'Check-in submitted at the gate', createdAt),
        historyEntry(
            'notification-sent',
            'Confirmation text sent to the driver',
            createdAt + 60_000,
        ),
        historyEntry(
            'employee-notification-queued',
            'Dock office notified',
            createdAt + 90_000,
        ),
    ];

    if (status !== 'pending') {
        entries.push(
            historyEntry(
                'status-changed',
                `Status changed to ${status}`,
                createdAt + between(1, 4) * HOUR,
                pick(STAFF_NAMES),
            ),
        );
    }

    if (erpStatus === 'failed') {
        entries.push(
            historyEntry(
                'notification-failed',
                'ERP sync failed: upstream timeout',
                createdAt + 5 * 60_000,
            ),
        );
    }

    return entries;
}

function makeCheckIn(index: number): CheckInRecord {
    // Denser in the recent past so "today" and the last week have something to show.
    const createdAt =
        MOCK_NOW.getTime() -
        Math.floor(Math.pow(random(), 2) * 30 * DAY) -
        index * 60_000;
    const isToday = MOCK_NOW.getTime() - createdAt < 10 * HOUR;
    const status: CheckInStatus = isToday
        ? weighted({ pending: 6, completed: 3, cancelled: 1 })
        : weighted({ pending: 1, completed: 16, cancelled: 2 });
    const erpStatus: CheckInErpStatus =
        status === 'completed'
            ? weighted({ synced: 12, failed: 1, pending: 1 })
            : 'pending';
    const [city, state] = pick(DESTINATIONS);
    const hasAccount = random() > 0.4;
    const name = driverName();

    return {
        uuid: uuid(),
        referenceNumber: reference('C'),
        status,
        erpStatus,
        location: { ...pick(MOCK_LOCATIONS), uuid: uuid() },
        customer: pick(CUSTOMERS),
        driver: {
            name,
            cellphone: cellphone(),
            email: hasAccount
                ? `${name.toLowerCase().replace(' ', '.')}@example.com`
                : null,
            licenseState: pick(STATES),
            licenseCountry: 'US',
            licenseExpirationDate: null,
            licenseLastFour: String(between(1000, 9999)),
            hasAccount,
            userUuid: hasAccount ? uuid() : null,
        },
        truck: {
            name: pick(TRUCKS),
            plate: plate(),
            plateState: pick(STATES),
            plateCountry: 'US',
            color: pick(COLORS),
        },
        trailer: {
            plate: plate(),
            plateState: pick(STATES),
            plateCountry: 'US',
            chute: pick<TrailerChute>(['n/a', 'center-chute', 'side-chute']),
        },
        emptyWeightLbs: random() > 0.3 ? between(28000, 36000) : null,
        destination: { city, state, country: 'US' },
        purchaseOrders: purchaseOrders(),
        loadingInstructions:
            random() > 0.6
                ? 'Keep reefer at 34F. Load pallets nose to tail.'
                : null,
        appointmentReference: random() > 0.7 ? reference('A') : null,
        appointmentUuid: null,
        appointmentLinkedVia: null,
        locale: random() > 0.75 ? 'es' : 'en',
        createdAt: iso(createdAt),
        completedAt: status === 'completed' ? iso(createdAt + HOUR) : null,
        cancelledAt: status === 'cancelled' ? iso(createdAt + HOUR) : null,
        cancelledReason:
            status === 'cancelled' ? 'Load canceled by the shipper.' : null,
        erpSyncedAt: erpStatus === 'synced' ? iso(createdAt + 2 * HOUR) : null,
        archivedAt: null,
        history: checkInHistory(createdAt, status, erpStatus),
    };
}

function makeAppointment(): AppointmentRecord {
    const offsetDays = between(-20, 10);
    const scheduledFor =
        MOCK_NOW.getTime() + offsetDays * DAY + between(-6, 6) * HOUR;
    const isPast = scheduledFor < MOCK_NOW.getTime();
    const status: AppointmentStatus = isPast
        ? weighted({
              completed: 8,
              'checked-in': 3,
              'no-show': 2,
              cancelled: 1,
          })
        : weighted({ scheduled: 9, cancelled: 1 });
    const createdAt = scheduledFor - between(1, 6) * DAY;
    const history = [historyEntry('created', 'Appointment booked', createdAt)];

    if (status !== 'scheduled') {
        history.push(
            historyEntry(
                'status-changed',
                `Status changed to ${status}`,
                Math.min(scheduledFor, MOCK_NOW.getTime()),
                pick(STAFF_NAMES),
            ),
        );
    }

    return {
        uuid: uuid(),
        referenceNumber: reference('A'),
        status,
        location: { ...pick(MOCK_LOCATIONS), uuid: uuid() },
        driver: {
            name: driverName(),
            cellphone: cellphone(),
            email: null,
            hasAccount: random() > 0.5,
        },
        scheduledFor: iso(scheduledFor),
        purchaseOrders: purchaseOrders(),
        cancelledReason:
            status === 'cancelled'
                ? 'Load was rescheduled by the shipper.'
                : null,
        checkIn:
            status === 'checked-in'
                ? { uuid: uuid(), referenceNumber: reference('C') }
                : null,
        createdAt: iso(createdAt),
        archivedAt: null,
        history,
    };
}

/** Only what the search modal reads; the users pages run on real data. */
type MockUser = Pick<DashboardUser, 'uuid' | 'name' | 'email' | 'createdAt'>;

function makeUser(index: number): MockUser {
    const name = driverName();

    return {
        uuid: uuid(),
        name,
        email: `${name.toLowerCase().replace(' ', '.')}${index}@example.com`,
        createdAt: iso(MOCK_NOW.getTime() - between(5, 400) * DAY),
    };
}

const byNewest = <T extends { createdAt: string }>(a: T, b: T): number =>
    b.createdAt.localeCompare(a.createdAt);

export const MOCK_CHECK_INS: CheckInRecord[] = Array.from(
    { length: 140 },
    (_, index) => makeCheckIn(index),
).sort(byNewest);

export const MOCK_APPOINTMENTS: AppointmentRecord[] = Array.from(
    { length: 80 },
    makeAppointment,
).sort((a, b) => b.scheduledFor.localeCompare(a.scheduledFor));

export const MOCK_USERS: MockUser[] = [
    ...Array.from({ length: 48 }, (_, index) => makeUser(1 + index)),
].sort(byNewest);

/** Check-ins per day for the last `days` days, oldest first, as `[YYYY-MM-DD, count]`. */
export function checkInsPerDay(days: number): [string, number][] {
    const counts = new Map<string, number>();

    for (let offset = days - 1; offset >= 0; offset--) {
        counts.set(iso(MOCK_NOW.getTime() - offset * DAY).slice(0, 10), 0);
    }

    for (const checkIn of MOCK_CHECK_INS) {
        const day = checkIn.createdAt.slice(0, 10);

        if (counts.has(day)) {
            counts.set(day, (counts.get(day) ?? 0) + 1);
        }
    }

    return [...counts.entries()];
}

export function isSameMockDay(timestamp: string): boolean {
    return timestamp.slice(0, 10) === MOCK_NOW.toISOString().slice(0, 10);
}
