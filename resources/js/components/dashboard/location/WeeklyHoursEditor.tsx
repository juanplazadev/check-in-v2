import { Link, router, useForm } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import { AlertTriangle, Check, Copy, Sparkles, Sun, Undo2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useId, useMemo, useRef, useState } from 'react';

import HoursTimePicker from '@/components/dashboard/location/HoursTimePicker';
import { StatusConfirmDialog } from '@/components/dashboard/RecordActionDialogs';
import type {
    LocationBookedAppointment,
    LocationHoursDay,
} from '@/components/dashboard/types';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Switch } from '@/components/ui/switch';
import { formatClockTime } from '@/lib/dashboard/format';
import {
    DAY_NAMES,
    END_OF_DAY,
    WEEKDAYS,
    WEEKEND,
    WEEK_ORDER,
    dayPercent,
    isAllDay,
    isOutsideHours,
    minutesOf,
    timeOf,
    summarizeWeek,
    todayAt,
} from '@/lib/dashboard/hours';
import dashboardRoutes from '@/routes/dashboard';
import type { RouteDefinition } from '@/wayfinder';

/** One day as the form holds it. A closed day keeps its last times, so turning it back on restores them. */
interface DayDraft {
    is_open: boolean;
    open_time: string;
    close_time: string;
}

/** Indexed by Carbon's day number (0 = Sunday), as the endpoint expects. */
type WeekDraft = DayDraft[];

const DEFAULT_OPEN = '09:00';
const DEFAULT_CLOSE = '17:00';

function draftFrom(hours: LocationHoursDay[]): WeekDraft {
    const byDay = new Map(hours.map((day) => [day.dayOfWeek, day]));

    return DAY_NAMES.map((_, day) => {
        const current = byDay.get(day);
        const isOpen =
            current !== undefined &&
            current.openTime !== null &&
            current.closeTime !== null;

        return {
            is_open: isOpen,
            open_time: isOpen ? current.openTime! : DEFAULT_OPEN,
            close_time: isOpen ? current.closeTime! : DEFAULT_CLOSE,
        };
    });
}

function hoursFrom(days: WeekDraft): LocationHoursDay[] {
    return days.map((day, dayOfWeek) => ({
        dayOfWeek,
        openTime: day.is_open ? day.open_time : null,
        closeTime: day.is_open ? day.close_time : null,
    }));
}

/** Two drafts of a day keep the same hours; a closed day's remembered times do not count. */
function sameDay(a: DayDraft, b: DayDraft): boolean {
    if (!a.is_open || !b.is_open) {
        return a.is_open === b.is_open;
    }

    return a.open_time === b.open_time && a.close_time === b.close_time;
}

/**
 * A new opening time. When it lands at or after the closing time, the closing time moves with it,
 * keeping the day as long as it was (8 hours if it was not valid), so the day never turns invalid.
 */
function withOpenTime(draft: DayDraft, openTime: string): Partial<DayDraft> {
    if (draft.close_time > openTime) {
        return { open_time: openTime };
    }

    const length = minutesOf(draft.close_time) - minutesOf(draft.open_time);
    const close = minutesOf(openTime) + (length > 0 ? length : 8 * 60);

    return {
        open_time: openTime,
        close_time: close >= 24 * 60 ? END_OF_DAY : timeOf(close),
    };
}

function dayError(day: DayDraft): string | null {
    if (!day.is_open) {
        return null;
    }

    if (day.open_time === '' || day.close_time === '') {
        return 'Choose both times.';
    }

    return day.close_time <= day.open_time
        ? 'Must close after it opens.'
        : null;
}

/**
 * Edits a location's weekly hours in place. Every change shows at once: the summary, each day's
 * bar on a 24-hour track, and, for appointments, the bookings the new hours would leave out.
 * Nothing is saved until Save hours (or Cmd/Ctrl+S); leaving with unsaved changes asks first.
 */
export default function WeeklyHoursEditor({
    action,
    hours,
    timezone,
    otherHours,
    otherLabel,
    bookedAppointments = [],
}: {
    /** The Wayfinder update action for this location's hours. */
    action: RouteDefinition<'put'>;
    hours: LocationHoursDay[];
    timezone: string;
    /** The other feature's week, offered as "Copy from {otherLabel} hours". */
    otherHours: LocationHoursDay[];
    otherLabel: string;
    /** Upcoming bookings to check the new hours against; appointment hours only. */
    bookedAppointments?: LocationBookedAppointment[];
}) {
    const form = useForm<{ days: WeekDraft }>({ days: draftFrom(hours) });
    // What is stored now, to tell changed days apart and to discard back to.
    const [saved, setSaved] = useState<WeekDraft>(() => draftFrom(hours));
    const [confirming, setConfirming] = useState(false);
    const today = todayAt(timezone);
    const days = form.data.days;

    const changedDays = WEEK_ORDER.filter(
        (day) => !sameDay(days[day], saved[day]),
    );
    const isDirty = changedDays.length > 0;
    const hasErrors = days.some((day) => dayError(day) !== null);
    const summary = summarizeWeek(hoursFrom(days));

    const conflicts = useMemo(
        () =>
            bookedAppointments.filter((appointment) => {
                const day = days[appointment.dayOfWeek];

                return isOutsideHours(
                    appointment.time,
                    day.is_open ? day.open_time : null,
                    day.is_open ? day.close_time : null,
                );
            }),
        [bookedAppointments, days],
    );

    const otherDraft = useMemo(() => draftFrom(otherHours), [otherHours]);
    const otherDiffers = otherDraft.some(
        (day, index) => !sameDay(day, days[index]),
    );

    const setDays = (update: (current: WeekDraft) => WeekDraft) => {
        form.setData('days', update(form.data.days));
        form.clearErrors();
    };

    const setDay = (day: number, changes: Partial<DayDraft>) =>
        setDays((current) =>
            current.map((draft, index) =>
                index === day ? { ...draft, ...changes } : draft,
            ),
        );

    const copyDay = (from: number, targets: number[]) =>
        setDays((current) =>
            current.map((draft, index) =>
                targets.includes(index) ? { ...current[from] } : draft,
            ),
        );

    const applyPreset = (preset: (day: number) => DayDraft) =>
        setDays((current) => current.map((_, day) => preset(day)));

    const discard = () => {
        form.setData('days', saved);
        form.clearErrors();
    };

    const save = () => {
        if (form.processing || !isDirty || hasErrors) {
            return;
        }

        const submitted = form.data.days;

        form.transform((data) => ({
            days: data.days.map((day) => ({
                is_open: day.is_open,
                open_time: day.is_open ? day.open_time : null,
                close_time: day.is_open ? day.close_time : null,
            })),
        }));

        form.submit(action, {
            preserveScroll: true,
            onSuccess: () => setSaved(submitted),
            onFinish: () => setConfirming(false),
        });
    };

    const requestSave = (event?: FormEvent) => {
        event?.preventDefault();

        if (conflicts.length > 0) {
            setConfirming(true);

            return;
        }

        save();
    };

    // Read by the listeners below, which are bound once.
    const latest = useRef({ isDirty, requestSave });

    useEffect(() => {
        latest.current = { isDirty, requestSave };
    });

    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if (
                (event.metaKey || event.ctrlKey) &&
                event.key.toLowerCase() === 's'
            ) {
                event.preventDefault();

                if (latest.current.isDirty) {
                    latest.current.requestSave();
                }
            }
        };

        const onBeforeUnload = (event: BeforeUnloadEvent) => {
            if (latest.current.isDirty) {
                event.preventDefault();
            }
        };

        // Saving is a PUT; only navigating away to another page asks.
        const removeBefore = router.on('before', (event) => {
            if (
                latest.current.isDirty &&
                event.detail.visit.method === 'get' &&
                !window.confirm('Leave without saving your hours changes?')
            ) {
                event.preventDefault();
            }
        });

        window.addEventListener('keydown', onKeyDown);
        window.addEventListener('beforeunload', onBeforeUnload);

        return () => {
            window.removeEventListener('keydown', onKeyDown);
            window.removeEventListener('beforeunload', onBeforeUnload);
            removeBefore();
        };
    }, []);

    return (
        <form onSubmit={requestSave}>
            <div className="mb-5 rounded-xl bg-gray-50 px-4 py-3 dark:bg-gray-900/30">
                <div className="mb-1 text-xs font-semibold text-gray-400 uppercase dark:text-gray-500">
                    {isDirty ? 'New hours' : 'Current hours'}
                </div>
                <p
                    aria-live="polite"
                    className="text-sm font-medium text-gray-800 dark:text-gray-100"
                >
                    {summary.join(' · ')}
                </p>
            </div>

            <div className="mb-3 flex flex-wrap items-center gap-2">
                <span className="mr-1 text-xs font-semibold text-gray-400 uppercase dark:text-gray-500">
                    Quick set
                </span>
                <PresetButton
                    icon={Sun}
                    label="Weekdays 9-5"
                    onClick={() =>
                        applyPreset((day) => ({
                            is_open: WEEKDAYS.includes(day),
                            open_time: '09:00',
                            close_time: '17:00',
                        }))
                    }
                />
                <PresetButton
                    icon={Sparkles}
                    label="Open 24/7"
                    onClick={() =>
                        applyPreset(() => ({
                            is_open: true,
                            open_time: '00:00',
                            close_time: END_OF_DAY,
                        }))
                    }
                />
                {otherDiffers && (
                    <PresetButton
                        icon={Copy}
                        label={`Copy from ${otherLabel} hours`}
                        onClick={() =>
                            setDays(() => otherDraft.map((day) => ({ ...day })))
                        }
                    />
                )}
            </div>

            <div className="divide-y divide-gray-100 overflow-hidden rounded-lg border border-gray-200 dark:divide-gray-700/60 dark:border-gray-700/60">
                {WEEK_ORDER.map((day) => (
                    <DayRow
                        key={day}
                        day={day}
                        draft={days[day]}
                        isToday={day === today}
                        isChanged={changedDays.includes(day)}
                        error={
                            dayError(days[day]) ??
                            form.errors[
                                `days.${day}.close_time` as keyof typeof form.errors
                            ] ??
                            form.errors[
                                `days.${day}.open_time` as keyof typeof form.errors
                            ] ??
                            null
                        }
                        bookings={bookedAppointments.filter(
                            (appointment) => appointment.dayOfWeek === day,
                        )}
                        conflicts={conflicts.filter(
                            (appointment) => appointment.dayOfWeek === day,
                        )}
                        onChange={(changes) => setDay(day, changes)}
                        onCopy={(targets) =>
                            copyDay(
                                day,
                                targets.filter((target) => target !== day),
                            )
                        }
                    />
                ))}
            </div>

            {conflicts.length > 0 && <ConflictCallout conflicts={conflicts} />}

            <SaveBar
                visible={isDirty}
                changedCount={changedDays.length}
                processing={form.processing}
                disabled={hasErrors}
                onDiscard={discard}
            />

            <StatusConfirmDialog
                open={confirming}
                onOpenChange={setConfirming}
                title="Save the new hours anyway?"
                description={`${conflicts.length} booked ${conflicts.length === 1 ? 'appointment falls' : 'appointments fall'} outside these hours. ${conflicts.length === 1 ? 'It stays' : 'They stay'} booked; reach out to the ${conflicts.length === 1 ? 'driver' : 'drivers'} if the time no longer works.`}
                keepLabel="Keep editing"
                confirmLabel="Save hours"
                busy={form.processing}
                onConfirm={save}
            />
        </form>
    );
}

function PresetButton({
    icon: Icon,
    label,
    onClick,
}: {
    icon: typeof Sun;
    label: string;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className="inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-gray-200 bg-white px-3 py-1 text-xs font-medium text-gray-600 transition hover:border-brand-green/40 hover:bg-brand-green/[0.06] hover:text-brand-green dark:border-gray-700/60 dark:bg-gray-800 dark:text-gray-300"
        >
            <Icon className="size-3.5" aria-hidden />
            {label}
        </button>
    );
}

function DayRow({
    day,
    draft,
    isToday,
    isChanged,
    error,
    bookings,
    conflicts,
    onChange,
    onCopy,
}: {
    day: number;
    draft: DayDraft;
    isToday: boolean;
    isChanged: boolean;
    error: string | null;
    bookings: LocationBookedAppointment[];
    conflicts: LocationBookedAppointment[];
    onChange: (changes: Partial<DayDraft>) => void;
    onCopy: (targets: number[]) => void;
}) {
    const fieldId = useId();
    const name = DAY_NAMES[day];
    const allDay = draft.is_open && isAllDay(draft.open_time, draft.close_time);
    const valid = draft.is_open && error === null;

    return (
        <div
            className={`grid grid-cols-[auto_1fr] items-center gap-x-4 gap-y-2 px-4 py-3 transition-colors sm:grid-cols-[9rem_auto_1fr_auto] ${
                isToday
                    ? 'bg-brand-green/[0.04] dark:bg-brand-green/[0.08]'
                    : ''
            }`}
        >
            <div className="flex items-center gap-2">
                <span
                    aria-hidden
                    className={`size-1.5 shrink-0 rounded-full transition-colors ${isChanged ? 'bg-amber-500' : 'bg-transparent'}`}
                />
                <label
                    htmlFor={`${fieldId}-open`}
                    className="text-sm font-medium text-gray-800 dark:text-gray-100"
                >
                    {name}
                </label>
                {isToday && (
                    <span className="rounded-full bg-brand-green/12 px-2 py-0.5 text-[11px] font-semibold text-brand-green">
                        Today
                    </span>
                )}
            </div>

            <div className="justify-self-end sm:justify-self-start">
                <Switch
                    id={`${fieldId}-open`}
                    checked={draft.is_open}
                    onCheckedChange={(checked) =>
                        onChange({ is_open: checked })
                    }
                    aria-label={`${name} open`}
                />
            </div>

            <div className="col-span-2 min-w-0 sm:col-span-1">
                {!draft.is_open ? (
                    <span className="text-sm text-gray-400 dark:text-gray-500">
                        Closed
                    </span>
                ) : (
                    <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
                        {allDay ? (
                            <span className="text-sm font-medium text-gray-700 dark:text-gray-200">
                                Open 24 hours
                            </span>
                        ) : (
                            <div className="flex items-center gap-2">
                                <HoursTimePicker
                                    label={`${name} opens`}
                                    value={draft.open_time}
                                    onChange={(time) =>
                                        onChange(withOpenTime(draft, time))
                                    }
                                />
                                <span className="text-gray-400">to</span>
                                <HoursTimePicker
                                    label={`${name} closes`}
                                    value={draft.close_time}
                                    min={draft.open_time}
                                    allowEndOfDay
                                    invalid={error !== null}
                                    onChange={(time) =>
                                        onChange({ close_time: time })
                                    }
                                />
                            </div>
                        )}
                        <label className="flex cursor-pointer items-center gap-1.5 text-xs font-medium text-gray-500 dark:text-gray-400">
                            <input
                                type="checkbox"
                                className="form-checkbox"
                                checked={allDay}
                                onChange={(event) =>
                                    onChange(
                                        event.target.checked
                                            ? {
                                                  open_time: '00:00',
                                                  close_time: END_OF_DAY,
                                              }
                                            : {
                                                  open_time: DEFAULT_OPEN,
                                                  close_time: DEFAULT_CLOSE,
                                              },
                                    )
                                }
                            />
                            24 hours
                        </label>
                    </div>
                )}

                <DayTrack
                    draft={valid ? draft : null}
                    bookings={bookings}
                    conflicts={conflicts}
                />

                {error && (
                    <p
                        role="alert"
                        className="mt-1.5 text-xs font-medium text-red-500"
                    >
                        {error}
                    </p>
                )}
            </div>

            <div className="col-span-2 justify-self-start sm:col-span-1 sm:justify-self-end">
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <button
                            type="button"
                            className="inline-flex cursor-pointer items-center gap-1 rounded-md px-1.5 py-1 text-xs font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700/60 dark:hover:text-gray-200"
                        >
                            <Copy className="size-3.5" aria-hidden />
                            Copy to...
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="min-w-44">
                        <DropdownMenuItem onSelect={() => onCopy(WEEK_ORDER)}>
                            All days
                        </DropdownMenuItem>
                        <DropdownMenuItem onSelect={() => onCopy(WEEKDAYS)}>
                            Weekdays (Mon-Fri)
                        </DropdownMenuItem>
                        <DropdownMenuItem onSelect={() => onCopy(WEEKEND)}>
                            Weekend (Sat-Sun)
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>
    );
}

/**
 * The day on a midnight-to-midnight track: the open span filled in, and each booking as a dot,
 * red when the hours leave it out. Lined up down the rows, it shows the shape of the week.
 */
function DayTrack({
    draft,
    bookings,
    conflicts,
}: {
    draft: DayDraft | null;
    bookings: LocationBookedAppointment[];
    conflicts: LocationBookedAppointment[];
}) {
    const left = draft ? dayPercent(draft.open_time) : 0;
    const right = draft ? dayPercent(draft.close_time) : 0;

    return (
        <div
            aria-hidden
            className="relative mt-2 h-1.5 rounded-full bg-gray-100 dark:bg-gray-700/60"
        >
            <div
                className="absolute inset-y-0 rounded-full bg-brand-green/70 transition-all duration-300 ease-out"
                style={{
                    left: `${left}%`,
                    width: `${Math.max(right - left, 0)}%`,
                }}
            />
            {bookings.map((booking) => (
                <span
                    key={booking.uuid}
                    title={`${formatClockTime(booking.time)} · ${booking.driversName}`}
                    className={`absolute top-1/2 size-2 -translate-x-1/2 -translate-y-1/2 rounded-full ring-2 ring-white dark:ring-gray-800 ${
                        conflicts.includes(booking)
                            ? 'bg-red-500'
                            : 'bg-gray-700 dark:bg-gray-200'
                    }`}
                    style={{ left: `${dayPercent(booking.time)}%` }}
                />
            ))}
        </div>
    );
}

const CONFLICT_PREVIEW_LIMIT = 5;

function ConflictCallout({
    conflicts,
}: {
    conflicts: LocationBookedAppointment[];
}) {
    const shown = conflicts.slice(0, CONFLICT_PREVIEW_LIMIT);
    const more = conflicts.length - shown.length;

    return (
        <div
            role="status"
            className="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-500/30 dark:bg-amber-500/10"
        >
            <div className="flex items-start gap-2">
                <AlertTriangle
                    className="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400"
                    aria-hidden
                />
                <div className="min-w-0 text-sm">
                    <p className="font-medium text-amber-900 dark:text-amber-200">
                        {conflicts.length} booked{' '}
                        {conflicts.length === 1
                            ? 'appointment falls'
                            : 'appointments fall'}{' '}
                        outside these hours
                    </p>
                    <p className="mt-0.5 text-amber-800/80 dark:text-amber-200/70">
                        They stay booked if you save. New bookings follow the
                        new hours.
                    </p>
                    <ul className="mt-2 space-y-1">
                        {shown.map((appointment) => (
                            <li key={appointment.uuid}>
                                <Link
                                    href={dashboardRoutes.appointments.show.url(
                                        appointment.uuid,
                                    )}
                                    className="text-amber-900 hover:underline dark:text-amber-100"
                                >
                                    <span className="tabular-nums">
                                        {format(
                                            parseISO(appointment.date),
                                            'EEE, MMM d',
                                        )}{' '}
                                        at {formatClockTime(appointment.time)}
                                    </span>
                                    <span className="text-amber-800/70 dark:text-amber-200/60">
                                        {' '}
                                        · {appointment.driversName} ·{' '}
                                        {appointment.referenceNumber}
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                    {more > 0 && (
                        <p className="mt-1 text-xs text-amber-800/70 dark:text-amber-200/60">
                            and {more} more
                        </p>
                    )}
                </div>
            </div>
        </div>
    );
}

function SaveBar({
    visible,
    changedCount,
    processing,
    disabled,
    onDiscard,
}: {
    visible: boolean;
    changedCount: number;
    processing: boolean;
    disabled: boolean;
    onDiscard: () => void;
}) {
    const isMac =
        typeof navigator !== 'undefined' &&
        /Mac|iPhone|iPad/.test(navigator.platform);

    return (
        <div
            aria-hidden={!visible}
            className={`sticky bottom-4 z-10 mt-5 transition-all duration-200 ease-out ${
                visible
                    ? 'translate-y-0 opacity-100'
                    : 'pointer-events-none translate-y-2 opacity-0'
            }`}
        >
            <div className="flex flex-wrap items-center gap-3 rounded-xl bg-gray-900 px-4 py-3 text-sm text-gray-100 shadow-lg dark:bg-gray-100 dark:text-gray-800">
                <span className="mr-auto">
                    {changedCount} {changedCount === 1 ? 'day' : 'days'} changed
                    <span className="ml-2 hidden text-xs text-gray-400 sm:inline dark:text-gray-500">
                        {isMac ? '⌘S' : 'Ctrl+S'} to save
                    </span>
                </span>
                <button
                    type="button"
                    onClick={onDiscard}
                    disabled={processing}
                    tabIndex={visible ? 0 : -1}
                    className="inline-flex cursor-pointer items-center gap-1.5 rounded-lg px-3 py-1.5 font-medium text-gray-300 hover:bg-white/10 hover:text-white disabled:opacity-50 dark:text-gray-600 dark:hover:bg-gray-900/10 dark:hover:text-gray-900"
                >
                    <Undo2 className="size-4" aria-hidden />
                    Discard
                </button>
                <button
                    type="submit"
                    disabled={processing || disabled}
                    tabIndex={visible ? 0 : -1}
                    className="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-brand-green px-3 py-1.5 font-medium text-white hover:bg-brand-green/90 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <Check className="size-4" aria-hidden />
                    {processing ? 'Saving...' : 'Save hours'}
                </button>
            </div>
        </div>
    );
}
