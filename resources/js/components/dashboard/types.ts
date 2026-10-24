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
    | 'appointment-deleted'
    | 'appointment-linked'
    | 'appointment-unlinked'
    | 'pdf-emailed'
    | 'location-user-attached'
    | 'location-user-detached'
    | 'check-ins-paused'
    | 'check-ins-resumed'
    | 'appointments-paused'
    | 'appointments-resumed'
    | 'location-deleted'
    | 'location-restored';

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

/**
 * What permanently deleting an appointment removes, from App\Dashboard\AppointmentDeletionImpact.
 * Only sent on a partial reload, when the delete dialog opens.
 */
export interface AppointmentDeletionImpact {
    historyEntries: number;
    notes: number;
    purchaseOrders: number;
    staffNotifications: number;
    hasShortLink: boolean;
    pendingClaims: number;
    checkInReference: string | null;
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
        /** The driver's account email; appointments store none of their own. */
        email: string | null;
        hasAccount: boolean;
    };
    scheduledFor: string;
    purchaseOrders: string[];
    cancelledReason: string | null;
    /** The check-in this booking turned into, once the driver arrived. */
    checkIn: { uuid: string; referenceNumber: string } | null;
    createdAt: string;
    /** When staff archived it, or null while it is live. */
    archivedAt: string | null;
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

/** A soft-deleted location on the restore list. Mirrors LocationRecordResolver::deleted(). */
export interface DashboardDeletedLocation {
    uuid: string;
    name: string;
    abbreviation: string;
    city: string;
    state: string;
    deletedAt: string | null;
}

export interface LocationStaffMember {
    uuid: string;
    name: string;
    email: string;
    roles: StaffRole[];
}

/** Where a location sits, for its map. Mirrors LocationRecordResolver::map(). */
export interface LocationMapPoint {
    latitude: number | null;
    longitude: number | null;
    /** Miles from the dock a driver may check in. */
    maxDistanceAllowed: number;
}

/** A date whose hours differ from the weekly schedule. Mirrors LocationRecordResolver::mergeClosures(). */
export interface LocationClosure {
    /** Y-m-d, in the location's timezone. */
    date: string;
    isClosed: boolean;
    /** HH:MM; null when closed all day. */
    openTime: string | null;
    closeTime: string | null;
    reason: string;
    appliesTo: ('checkIns' | 'appointments')[];
}

/** A public holiday drawn on the closures calendar. Will mirror the holidays API once it exists. */
export interface CalendarHoliday {
    /** Y-m-d. */
    date: string;
    name: string;
}

/** Today's status for check-ins or appointments. Mirrors LocationRecordResolver::today(). */
export interface LocationToday {
    isOpen: boolean;
    openTime: string | null;
    closeTime: string | null;
    hasOverride: boolean;
    reason: string | null;
}

/** Mirrors App\Enums\CheckInPauseDuration. */
export type CheckInPauseDuration =
    '30m' | '1h' | '2h' | 'rest-of-today' | 'until-resumed';

/** Staff have stopped new check-ins. Until is HH:MM in the location's time, or null until resumed. */
export interface LocationCheckInPause {
    until: string | null;
    reason: string | null;
}

/** Mirrors App\Enums\AppointmentPauseDuration. */
export type AppointmentPauseDuration =
    'rest-of-today' | '1d' | '3d' | '1w' | 'until-resumed';

/**
 * Staff have stopped new bookings. Until is an ISO datetime in the location's time, since a pause
 * can run for days, or null until resumed.
 */
export interface LocationAppointmentPause {
    until: string | null;
    reason: string | null;
}

/** Mirrors LocationRecordResolver::overview(). */
export interface LocationOverview {
    checkIns: LocationToday & { pause: LocationCheckInPause | null };
    appointments: LocationToday & { pause: LocationAppointmentPause | null };
    upcomingClosures: LocationClosure[];
    map: LocationMapPoint;
}

/** Mirrors LocationRecordResolver::details(). */
export interface LocationDetails {
    email: string;
    address: {
        street1: string;
        street2: string | null;
        city: string;
        state: string;
        zipCode: string;
        zipCode4: string;
        country: string;
    };
    map: LocationMapPoint;
    createdAt: string;
}

/** Mirrors LocationRecordResolver::users(). */
export interface LocationUsers {
    attached: LocationStaffMember[];
    /** Staff who see every location through view all locations; the pivot has no say over them. */
    allLocations: LocationStaffMember[];
}

/** One weekday's hours, Monday first. dayOfWeek is Carbon's (0 = Sunday). Null times mean closed. */
export interface LocationHoursDay {
    dayOfWeek: number;
    openTime: string | null;
    closeTime: string | null;
}

/** Mirrors LocationRecordResolver::checkInOptions(). */
export interface LocationCheckInOptions {
    formFields: CheckInFormFields;
    notificationEmails: string[];
    maxDistanceAllowed: number;
}

/** Mirrors LocationRecordResolver::appointmentOptions(). */
export interface LocationAppointmentOptions {
    booking: {
        maxDaysAhead: number;
        slotIntervalMinutes: number;
        minLeadTimeMinutes: number;
        bufferBeforeCloseMinutes: number;
    };
    notificationEmails: string[];
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
