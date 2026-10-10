import type { StaffRole } from '@/types/staff';

/** Mirrors App\Enums\CheckInStatus. */
export type CheckInStatus = 'pending' | 'completed' | 'cancelled';

/** Mirrors App\Enums\CheckInErpStatus. */
export type CheckInErpStatus = 'pending' | 'synced' | 'failed';

/** Mirrors App\Enums\AppointmentStatus. */
export type AppointmentStatus =
    'scheduled' | 'cancelled' | 'completed' | 'no-show' | 'checked-in';

/** Mirrors App\Enums\TrailerChute. */
export type TrailerChute = 'n/a' | 'center-chute' | 'side-chute';

/** Mirrors App\Enums\RecordHistoryEvent. */
export type RecordHistoryEvent =
    | 'created'
    | 'notification-sent'
    | 'notification-failed'
    | 'employee-notification-queued'
    | 'status-changed'
    | 'claimed'
    | 'account-deleted';

export interface LocationSummary {
    id: number;
    name: string;
}

export interface RecordHistoryEntry {
    id: number;
    event: RecordHistoryEvent;
    description: string;
    actor: string | null;
    createdAt: string;
}

export interface CheckInRecord {
    uuid: string;
    referenceNumber: string;
    status: CheckInStatus;
    erpStatus: CheckInErpStatus;
    location: LocationSummary;
    customer: string;
    driver: {
        name: string;
        cellphone: string;
        email: string | null;
        licenseState: string;
        /** Only the last four digits ever reach the browser. */
        licenseLastFour: string;
        hasAccount: boolean;
    };
    truck: {
        name: string;
        plate: string;
        plateState: string;
        color: string;
    };
    trailer: {
        plate: string;
        plateState: string;
        chute: TrailerChute;
    };
    emptyWeightLbs: number | null;
    destination: {
        city: string;
        state: string;
        country: string;
    };
    purchaseOrders: string[];
    loadingInstructions: string | null;
    appointmentReference: string | null;
    createdAt: string;
    history: RecordHistoryEntry[];
}

export interface AppointmentRecord {
    uuid: string;
    referenceNumber: string;
    status: AppointmentStatus;
    location: LocationSummary;
    driver: {
        name: string;
        cellphone: string;
        hasAccount: boolean;
    };
    scheduledFor: string;
    purchaseOrders: string[];
    cancelledReason: string | null;
    /** The check-in this booking turned into, once the driver arrived. */
    checkIn: { uuid: string; referenceNumber: string } | null;
    createdAt: string;
    history: RecordHistoryEntry[];
}

/** A location as the users pages show it: enough for a summary card. */
export interface UserLocation extends LocationSummary {
    abbreviation: string;
    city: string;
    state: string;
    timezone: string;
    phone: string;
    phoneExt: string | null;
    isActive: boolean;
    isCheckInsEnabled: boolean;
    isAppointmentsEnabled: boolean;
}

/** Mirrors App\Dashboard\UserRecordResolver::record(). */
export interface DashboardUser {
    id: number;
    name: string;
    email: string;
    cellphone: string | null;
    /** Empty for drivers. */
    roles: StaffRole[];
    /** Holds a role that sees every location, whatever is attached. */
    hasAllLocations: boolean;
    /** The locations a staff member works at (location_user). */
    locations: UserLocation[];
    /** A driver's appointment booking default. */
    defaultLocation: UserLocation | null;
    emailVerifiedAt: string | null;
    createdAt: string;
    lastActiveAt: string | null;
    checkInsCount: number;
    appointmentsCount: number;
}
