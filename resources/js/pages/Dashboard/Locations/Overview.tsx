import { Head, Link, router } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    ArrowRight,
    CalendarClock,
    PauseCircle,
    PlayCircle,
    Power,
    Trash2,
    Truck,
    Users,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';

import LocationAppointmentPauseController from '@/actions/App/Http/Controllers/Dashboard/LocationAppointmentPauseController';
import LocationCheckInPauseController from '@/actions/App/Http/Controllers/Dashboard/LocationCheckInPauseController';
import LocationDeleteController from '@/actions/App/Http/Controllers/Dashboard/LocationDeleteController';
import LocationStatusController from '@/actions/App/Http/Controllers/Dashboard/LocationStatusController';
import ClosureList from '@/components/dashboard/location/ClosureList';
import LocationMap from '@/components/dashboard/location/LocationMap';
import type { PauseDurationOption } from '@/components/dashboard/location/PauseDialog';
import PauseDialog from '@/components/dashboard/location/PauseDialog';
import SectionHeader, {
    SectionGroup,
} from '@/components/dashboard/location/SectionHeader';
import {
    DeleteRecordDialog,
    ImpactList,
    StatusConfirmDialog,
} from '@/components/dashboard/RecordActionDialogs';
import type {
    AppointmentPauseDuration,
    CheckInPauseDuration,
    DashboardLocationSummary,
    LocationAppointmentPause,
    LocationCheckInPause,
    LocationOverview,
    LocationToday,
} from '@/components/dashboard/types';
import { useCan } from '@/hooks/useCan';
import DashboardLayout from '@/layouts/DashboardLayout';
import LocationLayout from '@/layouts/LocationLayout';
import {
    formatClockTime,
    formatWallDateTime,
    timezoneLabel,
} from '@/lib/dashboard/format';
import dashboardRoutes from '@/routes/dashboard';

interface LocationOverviewProps {
    location: DashboardLocationSummary;
    overview: LocationOverview;
}

type Feature = 'checkIns' | 'appointments';

const FEATURE_FIELDS: Record<
    Feature,
    'is_checkins_enabled' | 'is_appointments_enabled'
> = {
    checkIns: 'is_checkins_enabled',
    appointments: 'is_appointments_enabled',
};

const FEATURE_LABELS: Record<Feature, string> = {
    checkIns: 'check-ins',
    appointments: 'appointments',
};

const APPOINTMENT_DURATIONS: PauseDurationOption<AppointmentPauseDuration>[] = [
    { value: 'rest-of-today', label: 'Rest of today' },
    { value: '1d', label: '1 day' },
    { value: '3d', label: '3 days' },
    { value: '1w', label: '1 week' },
    { value: 'until-resumed', label: 'Until resumed' },
];

/** Rest of today needs a closing time today, and reads as that time. */
function checkInDurations(
    closeTime: string | null,
): PauseDurationOption<CheckInPauseDuration>[] {
    return [
        { value: '30m', label: '30 minutes' },
        { value: '1h', label: '1 hour' },
        { value: '2h', label: '2 hours' },
        ...(closeTime !== null
            ? [
                  {
                      value: 'rest-of-today' as const,
                      label: `Until ${formatClockTime(closeTime)}`,
                  },
              ]
            : []),
        { value: 'until-resumed', label: 'Until resumed' },
    ];
}

function pauseLine(
    pause: LocationCheckInPause | LocationAppointmentPause,
    formatUntil: (until: string) => string,
): string {
    const until =
        pause.until === null
            ? 'until resumed'
            : `until ${formatUntil(pause.until)}`;

    return `Paused ${until}${pause.reason ? `: ${pause.reason}` : ''}`;
}

function SectionLink({
    href,
    children,
}: {
    href: string;
    children: ReactNode;
}) {
    return (
        <Link
            href={href}
            className="inline-flex items-center gap-1 text-sm font-medium text-brand-green hover:underline"
        >
            {children}
            <ArrowRight className="size-3.5" aria-hidden />
        </Link>
    );
}

function TodayTile({
    icon: Icon,
    label,
    enabled,
    today,
    pauseText,
    offText,
    href,
    actions,
}: {
    icon: LucideIcon;
    label: string;
    enabled: boolean;
    today: LocationToday;
    /** Staff have stopped new check-ins or bookings; null when not paused. */
    pauseText: string | null;
    /** What drivers or guests cannot do while the feature is turned off. */
    offText: string;
    href: string;
    actions?: ReactNode;
}) {
    const hours =
        today.openTime !== null && today.closeTime !== null
            ? `${formatClockTime(today.openTime)} - ${formatClockTime(today.closeTime)}`
            : 'Closed today';

    const status = !enabled
        ? {
              text: 'Turned off',
              className: 'bg-rose-500 text-white',
          }
        : pauseText !== null
          ? {
                text: 'Paused',
                className: 'bg-amber-500/12 text-amber-700 dark:text-amber-400',
            }
          : today.isOpen
            ? {
                  text: 'Open now',
                  className:
                      'bg-emerald-500/12 text-emerald-700 dark:text-emerald-400',
              }
            : {
                  text: 'Closed now',
                  className: 'bg-rose-500/12 text-rose-600 dark:text-rose-400',
              };

    return (
        <div
            className={`rounded-lg border p-4 ${
                enabled
                    ? 'border-gray-200 dark:border-gray-700/60'
                    : 'border-rose-300 bg-rose-50/60 dark:border-rose-500/40 dark:bg-rose-500/8'
            }`}
        >
            <div className="flex items-center justify-between gap-3">
                <span className="inline-flex items-center gap-2 font-semibold text-gray-800 dark:text-gray-100">
                    <Icon className="size-4 text-gray-400" aria-hidden />
                    {label}
                </span>
                <span
                    className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold ${status.className}`}
                >
                    {!enabled && <Power className="size-3" aria-hidden />}
                    {status.text}
                </span>
            </div>
            <div
                className={`mt-3 text-lg font-bold tabular-nums ${
                    enabled
                        ? 'text-gray-800 dark:text-gray-100'
                        : 'text-gray-400 line-through dark:text-gray-500'
                }`}
            >
                {hours}
            </div>
            {enabled ? (
                <div className="mt-1 min-h-5 text-sm text-gray-500 dark:text-gray-400">
                    {pauseText ??
                        (today.hasOverride &&
                            (today.reason ?? 'Special hours today'))}
                </div>
            ) : (
                <div className="mt-1 min-h-5 text-sm font-medium text-rose-700 dark:text-rose-400">
                    {offText}
                </div>
            )}
            <div className="mt-3 flex flex-wrap items-center justify-between gap-2">
                <SectionLink href={href}>Weekly hours</SectionLink>
                {actions && (
                    <div className="flex flex-wrap items-center gap-2">
                        {actions}
                    </div>
                )}
            </div>
        </div>
    );
}

const TILE_BUTTON =
    'btn-sm gap-1.5 border-gray-200 bg-white text-gray-600 hover:border-gray-300 disabled:opacity-50 dark:border-gray-700/60 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-gray-600';

/** Pause or resume, and turn on or off, as each permission allows. */
function TileActions({
    enabled,
    paused,
    canPause,
    canToggle,
    onPause,
    onResume,
    onToggle,
}: {
    enabled: boolean;
    paused: boolean;
    canPause: boolean;
    canToggle: boolean;
    onPause: () => void;
    onResume: () => void;
    onToggle: () => void;
}) {
    if (!canPause && !canToggle) {
        return null;
    }

    return (
        <>
            {canPause &&
                enabled &&
                (paused ? (
                    <button
                        type="button"
                        onClick={onResume}
                        className="btn-sm gap-1.5 bg-emerald-500 text-white hover:bg-emerald-600"
                    >
                        <PlayCircle className="size-3.5" aria-hidden />
                        Resume
                    </button>
                ) : (
                    <button
                        type="button"
                        onClick={onPause}
                        className={TILE_BUTTON}
                    >
                        <PauseCircle className="size-3.5" aria-hidden />
                        Pause
                    </button>
                ))}
            {canToggle && (
                <button
                    type="button"
                    onClick={onToggle}
                    className={
                        enabled
                            ? TILE_BUTTON
                            : 'btn-sm gap-1.5 bg-emerald-500 text-white hover:bg-emerald-600'
                    }
                >
                    <Power className="size-3.5" aria-hidden />
                    {enabled ? 'Turn off' : 'Turn on'}
                </button>
            )}
        </>
    );
}

export default function LocationOverviewPage({
    location,
    overview,
}: LocationOverviewProps) {
    const routes = dashboardRoutes.locations;
    const can = useCan();
    const canToggle = can('manage location status');
    const checkInPause = overview.checkIns.pause;
    const appointmentPause = overview.appointments.pause;
    const enabled: Record<Feature, boolean> = {
        checkIns: location.isCheckInsEnabled,
        appointments: location.isAppointmentsEnabled,
    };

    const [pausing, setPausing] = useState<Feature | null>(null);
    const [resuming, setResuming] = useState<Feature | null>(null);
    const [toggling, setToggling] = useState<Feature | null>(null);
    // Kept apart from the dialog state, so the copy holds while a dialog closes.
    const [lastResumed, setLastResumed] = useState<Feature>('checkIns');
    const [lastToggled, setLastToggled] = useState<{
        feature: Feature;
        enable: boolean;
    }>({ feature: 'checkIns', enable: false });
    const [busy, setBusy] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [deleteError, setDeleteError] = useState<string | null>(null);

    const openResume = (feature: Feature) => {
        setLastResumed(feature);
        setResuming(feature);
    };

    const openToggle = (feature: Feature) => {
        setLastToggled({ feature, enable: !enabled[feature] });
        setToggling(feature);
    };

    const resume = () => {
        router.delete(
            lastResumed === 'checkIns'
                ? LocationCheckInPauseController.destroy.url(location.uuid)
                : LocationAppointmentPauseController.destroy.url(location.uuid),
            {
                preserveScroll: true,
                onStart: () => setBusy(true),
                onFinish: () => {
                    setBusy(false);
                    setResuming(null);
                },
            },
        );
    };

    const toggle = () => {
        router.patch(
            LocationStatusController.update.url(location.uuid),
            { [FEATURE_FIELDS[lastToggled.feature]]: lastToggled.enable },
            {
                preserveScroll: true,
                onStart: () => setBusy(true),
                onFinish: () => {
                    setBusy(false);
                    setToggling(null);
                },
            },
        );
    };

    const destroy = (reason: string) => {
        router.delete(LocationDeleteController.url(location.uuid), {
            data: { reason },
            onStart: () => {
                setDeleting(true);
                setDeleteError(null);
            },
            onError: (errors) =>
                setDeleteError(errors.reason ?? errors.location ?? null),
            onFinish: () => setDeleting(false),
        });
    };

    const changeDeleteOpen = (open: boolean) => {
        setDeleteOpen(open);

        if (!open) {
            setDeleteError(null);
        }
    };

    const toggleLabel = FEATURE_LABELS[lastToggled.feature];
    const turningOff = !lastToggled.enable;
    const toggleDescription = turningOff
        ? lastToggled.feature === 'checkIns'
            ? `Drivers can no longer check in at ${location.name}. Check-ins already submitted are kept.`
            : `Guests can no longer book appointments at ${location.name}. Appointments already booked are kept.`
        : lastToggled.feature === 'checkIns'
          ? `Drivers can check in at ${location.name} again, during its posted hours.`
          : `Guests can book appointments at ${location.name} again, during its posted hours.`;

    return (
        <>
            <Head title={location.name} />

            <SectionHeader
                title="Overview"
                description={`Today at a glance, in ${timezoneLabel(location.timezone)} time.`}
            />

            <div className="mb-8 grid gap-4 sm:grid-cols-2">
                <TodayTile
                    icon={Truck}
                    label="Check-ins"
                    enabled={location.isCheckInsEnabled}
                    today={overview.checkIns}
                    pauseText={
                        checkInPause !== null
                            ? pauseLine(checkInPause, formatClockTime)
                            : null
                    }
                    offText="Drivers can't check in here until it's turned back on."
                    href={routes.checkInHours.url(location.uuid)}
                    actions={
                        <TileActions
                            enabled={location.isCheckInsEnabled}
                            paused={checkInPause !== null}
                            canPause={can('pause check-ins')}
                            canToggle={canToggle}
                            onPause={() => setPausing('checkIns')}
                            onResume={() => openResume('checkIns')}
                            onToggle={() => openToggle('checkIns')}
                        />
                    }
                />
                <TodayTile
                    icon={CalendarClock}
                    label="Appointments"
                    enabled={location.isAppointmentsEnabled}
                    today={overview.appointments}
                    pauseText={
                        appointmentPause !== null
                            ? pauseLine(appointmentPause, formatWallDateTime)
                            : null
                    }
                    offText="Guests can't book here until it's turned back on."
                    href={routes.appointmentHours.url(location.uuid)}
                    actions={
                        <TileActions
                            enabled={location.isAppointmentsEnabled}
                            paused={appointmentPause !== null}
                            canPause={can('pause appointments')}
                            canToggle={canToggle}
                            onPause={() => setPausing('appointments')}
                            onResume={() => openResume('appointments')}
                            onToggle={() => openToggle('appointments')}
                        />
                    }
                />
            </div>
            <div className="grid gap-8 lg:grid-cols-2">
                <div>
                    <SectionGroup title="Upcoming closures">
                        <ClosureList
                            closures={overview.upcomingClosures}
                            empty="No closures or special hours coming up."
                        />
                        <div className="mt-3">
                            <SectionLink
                                href={routes.closures.url(location.uuid)}
                            >
                                All closures & holidays
                            </SectionLink>
                        </div>
                    </SectionGroup>

                    <SectionGroup title="Users">
                        <div className="flex items-center justify-between gap-3 rounded-lg border border-gray-200 p-4 dark:border-gray-700/60">
                            <span className="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                                <Users
                                    className="size-4 text-gray-400"
                                    aria-hidden
                                />
                                {location.staffCount === 1
                                    ? '1 user attached'
                                    : `${location.staffCount} users attached`}
                            </span>
                            <SectionLink href={routes.users.url(location.uuid)}>
                                Manage
                            </SectionLink>
                        </div>
                    </SectionGroup>
                </div>

                <SectionGroup title="Location">
                    <LocationMap point={overview.map} />
                </SectionGroup>
            </div>

            {can('delete locations') && (
                <div className="mt-8">
                    <SectionGroup title="Danger zone">
                        <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-rose-200 p-4 dark:border-rose-500/30">
                            <p className="text-sm text-gray-600 dark:text-gray-300">
                                Remove {location.name} from the dashboard and
                                from drivers and guests. Its history is kept,
                                and an admin can restore it.
                            </p>
                            <button
                                type="button"
                                onClick={() => setDeleteOpen(true)}
                                className="btn-sm gap-1.5 bg-rose-500 text-white hover:bg-rose-600"
                            >
                                <Trash2 className="size-3.5" aria-hidden />
                                Delete location
                            </button>
                        </div>
                    </SectionGroup>
                </div>
            )}

            <PauseDialog
                open={pausing === 'checkIns'}
                onOpenChange={(open) => setPausing(open ? 'checkIns' : null)}
                feature="check-ins"
                description={`Drivers cannot check in at ${location.name} until the pause ends, and they see your reason. Appointments are not affected.`}
                placeholder="Yard is full"
                durations={checkInDurations(overview.checkIns.closeTime)}
                url={LocationCheckInPauseController.store.url(location.uuid)}
            />
            <PauseDialog
                open={pausing === 'appointments'}
                onOpenChange={(open) =>
                    setPausing(open ? 'appointments' : null)
                }
                feature="appointments"
                description={`Guests cannot book appointments at ${location.name}, on any day, until the pause ends, and they see your reason. Appointments already booked and check-ins are not affected.`}
                placeholder="Calendar is full"
                durations={APPOINTMENT_DURATIONS}
                url={LocationAppointmentPauseController.store.url(
                    location.uuid,
                )}
            />
            <StatusConfirmDialog
                open={resuming !== null}
                onOpenChange={(open) => !open && setResuming(null)}
                title={`Resume ${FEATURE_LABELS[lastResumed]}?`}
                description={
                    lastResumed === 'checkIns'
                        ? `Drivers can check in at ${location.name} again right away, during its posted hours.`
                        : `Guests can book appointments at ${location.name} again right away, during its posted hours.`
                }
                keepLabel="Keep paused"
                confirmLabel={`Resume ${FEATURE_LABELS[lastResumed]}`}
                busy={busy}
                onConfirm={resume}
            />
            <StatusConfirmDialog
                open={toggling !== null}
                onOpenChange={(open) => !open && setToggling(null)}
                title={`Turn ${turningOff ? 'off' : 'on'} ${toggleLabel}?`}
                description={toggleDescription}
                keepLabel="Cancel"
                confirmLabel={`Turn ${turningOff ? 'off' : 'on'} ${toggleLabel}`}
                busy={busy}
                onConfirm={toggle}
            />
            <DeleteRecordDialog
                open={deleteOpen}
                onOpenChange={changeDeleteOpen}
                referenceNumber={location.name}
                title={`Delete ${location.name}?`}
                description="The location is taken out of service everywhere at once. Nothing is erased, so an admin can restore it from the locations table."
                impact={
                    <>
                        <ImpactList
                            title="Removed"
                            tone="danger"
                            items={[
                                {
                                    label: 'Drivers can no longer check in here, and guests can no longer book',
                                    active: true,
                                },
                                {
                                    label: 'The location leaves the locations table and the dashboard filters',
                                    active: true,
                                },
                            ]}
                        />
                        <ImpactList
                            title="Kept"
                            tone="neutral"
                            items={[
                                {
                                    label: 'Past check-ins and appointments, still showing this location',
                                    active: true,
                                },
                                {
                                    label: 'Attached users, hours, closures and history, ready for a restore',
                                    active: true,
                                },
                            ]}
                        />
                    </>
                }
                placeholder="Why is this location being deleted?"
                keepLabel="Keep location"
                confirmLabel="Delete location"
                deleting={deleting}
                error={deleteError}
                onConfirm={destroy}
            />
        </>
    );
}

LocationOverviewPage.layout = [DashboardLayout, LocationLayout];
