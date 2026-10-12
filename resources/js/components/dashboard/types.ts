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

/** Mirrors App\Enums\CheckInColumn: the check-in table columns a user may toggle. */
export type CheckInColumn =
    | 'reference'
    | 'driver'
    | 'customer'
    | 'location'
    | 'equipment'
    | 'erpStatus'
    | 'status'
    | 'date'
    | 'cellphone'
    | 'truckName'
    | 'trailerChute'
    | 'destination'
    | 'purchaseOrders'
    | 'appointmentReference'
    | 'truckColor'
    | 'emptyWeight'
    | 'truckPlateRegion'
    | 'trailerPlateRegion'
    | 'licenseRegion'
    | 'licenseExpiration';

/** Mirrors App\Enums\AppointmentColumn: the appointment table columns a user may toggle. */
export type AppointmentColumn =
    | 'reference'
    | 'date'
    | 'driver'
    | 'cellphone'
    | 'location'
    | 'purchaseOrders'
    | 'status'
    | 'checkIn';

/** Mirrors App\Enums\RecordHistoryEvent. */
export type RecordHistoryEvent =
    | 'created'
    | 'notification-sent'
    | 'notification-failed'
    | 'notification-skipped'
    | 'employee-notification-queued'
    | 'status-changed'
    | 'claimed'
    | 'account-deleted'
    | 'note-added'
    | 'license-viewed'
    | 'details-edited'
    | 'reinstated'
    | 'archived'
    | 'restored'
    | 'check-in-deleted'
    | 'appointment-linked'
    | 'appointment-unlinked';

/** An internal staff note on a check-in, newest first on the detail page. */
export interface CheckInNote {
    id: number;
    /** Null once the author's account is deleted. */
    author: string | null;
    body: string;
    createdAt: string;
}

export interface LocationSummary {
    id: number;
    name: string;
}

/** One field a staff edit changed. Empty values are null; a redacted field carries no values. */
export interface RecordHistoryChange {
    field: string;
    label: string;
    from: string | null;
    to: string | null;
    redacted: boolean;
}

export interface RecordHistoryEntry {
    id: number;
    event: RecordHistoryEvent;
    description: string;
    /** The fields a 'details-edited' entry changed; null for every other event. */
    changes: RecordHistoryChange[] | null;
    actor: string | null;
    createdAt: string;
}

/** A location's optional check-in fields, as Location::checkInFormFields() returns them. */
export interface CheckInFormFields {
    showTruckColor: boolean;
    showEmptyWeightLbs: boolean;
    showTruckPlateState: boolean;
    showTruckPlateCountry: boolean;
    showTrailerPlateState: boolean;
    showTrailerPlateCountry: boolean;
    showDriversLicenseState: boolean;
    showDriversLicenseCountry: boolean;
    showDriversLicenseExpirationDate: boolean;
}

/**
 * What permanently deleting a check-in removes, from App\Dashboard\CheckInDeletionImpact. Only
 * sent on a partial reload, when the delete dialog opens.
 */
export interface CheckInDeletionImpact {
    historyEntries: number;
    notes: number;
    purchaseOrders: number;
    staffNotifications: number;
    hasShortLink: boolean;
    pendingClaims: number;
    appointmentReference: string | null;
    driverHasAccount: boolean;
    archived: boolean;
}

export interface CheckInRecord {
    uuid: string;
    referenceNumber: string;
    status: CheckInStatus;
    erpStatus: CheckInErpStatus;
    location: LocationSummary & { uuid: string };
    customer: string;
    driver: {
        name: string;
        cellphone: string;
        email: string | null;
        licenseState: string;
        licenseCountry: string;
        /** A calendar date (Y-m-d), or null where the location does not collect it. */
        licenseExpirationDate: string | null;
        /** Only the last four digits ever reach the browser. */
        licenseLastFour: string;
        hasAccount: boolean;
        /** The driver's account, for linking to their user page. Null for guests. */
        userUuid: string | null;
    };
    truck: {
        name: string;
        plate: string;
        plateState: string;
        plateCountry: string;
        color: string;
    };
    trailer: {
        plate: string;
        plateState: string;
        plateCountry: string;
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
    appointmentUuid: string | null;
    /** How the booking was tied on: the driver converted it, or staff linked it (mirrors App\Enums\AppointmentLinkMethod). */
    appointmentLinkedVia: 'conversion' | 'staff' | null;
    /** The language the driver checked in with: one of config('app.locales'), e.g. 'es'. */
    locale: string;
    createdAt: string;
    completedAt: string | null;
    cancelledAt: string | null;
    /** Why staff cancelled it; set only on a cancelled check-in. */
    cancelledReason: string | null;
    erpSyncedAt: string | null;
    /** Set when the check-in is soft-deleted. */
    archivedAt: string | null;
    history: RecordHistoryEntry[];
}

export interface AppointmentRecord {
    uuid: string;
    referenceNumber: string;
    status: AppointmentStatus;
    location: LocationSummary & { uuid: string };
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
    uuid: string;
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

/** Mirrors App\Dashboard\LocationRecordResolver::summary(). */
export interface DashboardLocationSummary {
    uuid: string;
    name: string;
    abbreviation: string;
    city: string;
    state: string;
    timezone: string;
    phone: string;
    phoneExt: string | null;
    isActive: boolean;
    isCheckInsEnabled: boolean;
    isAppointmentsEnabled: boolean;
    staffCount: number;
}

export interface LocationStaffMember {
    uuid: string;
    name: string;
    email: string;
    roles: StaffRole[];
}

/** Mirrors App\Dashboard\LocationRecordResolver::record(). */
export interface DashboardLocation extends DashboardLocationSummary {
    email: string;
    address: {
        street1: string;
        street2: string | null;
        city: string;
        state: string;
        zipCode: string;
        country: string;
    };
    /** Miles from the dock a driver may check in. */
    maxDistanceAllowed: number;
    appointmentBooking: {
        maxDaysAhead: number;
        slotIntervalMinutes: number;
        minLeadTimeMinutes: number;
        bufferBeforeCloseMinutes: number;
    };
    createdAt: string;
    staff: LocationStaffMember[];
}

/** A booking the link dialog suggests, from App\Dashboard\AppointmentLinkCandidates. */
export interface AppointmentLinkSuggestion {
    uuid: string;
    referenceNumber: string;
    scheduledFor: string;
    driverName: string;
    cellphoneLastFour: string;
    phoneMatch: boolean;
    nameMatch: boolean;
}
