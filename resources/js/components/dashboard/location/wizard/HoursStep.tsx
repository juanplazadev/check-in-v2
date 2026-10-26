import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';

import { hours } from '@/actions/App/Http/Controllers/Dashboard/LocationWizardController';
import HoursTimePicker from '@/components/dashboard/location/HoursTimePicker';
import type { DayDraft } from '@/components/dashboard/location/WeeklyHoursEditor';
import {
    dayError,
    hoursFrom,
    withOpenTime,
} from '@/components/dashboard/location/WeeklyHoursEditor';
import WizardFooter from '@/components/dashboard/location/wizard/WizardFooter';
import type {
    LocationWizardDraft,
    LocationWizardHoursDay,
} from '@/components/dashboard/types';
import { Switch } from '@/components/ui/switch';
import {
    DAY_NAMES,
    END_OF_DAY,
    WEEK_ORDER,
    isAllDay,
    summarizeWeek,
} from '@/lib/dashboard/hours';

type WeekKey = 'checkins' | 'appointments';

/** A closed day sends no times, as the Hours pages do. */
function payloadOf(days: DayDraft[]) {
    return days.map((day) => ({
        is_open: day.is_open,
        open_time: day.is_open ? day.open_time : null,
        close_time: day.is_open ? day.close_time : null,
    }));
}

function sameWeek(a: DayDraft[], b: DayDraft[]): boolean {
    return a.every(
        (day, index) =>
            day.is_open === b[index].is_open &&
            (!day.is_open ||
                (day.open_time === b[index].open_time &&
                    day.close_time === b[index].close_time)),
    );
}

/** Whether every open day keeps the same hours, so one pair of times can stand for the week. */
function sharesHours(days: DayDraft[]): boolean {
    const open = days.filter((day) => day.is_open);

    return open.every(
        (day) =>
            day.open_time === open[0].open_time &&
            day.close_time === open[0].close_time,
    );
}

/** An opening and a closing time, with a 24 hours option. */
function TimeRange({
    label,
    draft,
    error,
    onChange,
}: {
    label: string;
    draft: DayDraft;
    error: string | null;
    onChange: (changes: Partial<DayDraft>) => void;
}) {
    const allDay = isAllDay(draft.open_time, draft.close_time);

    return (
        <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
            {allDay ? (
                <span className="text-sm font-medium text-gray-700 dark:text-gray-200">
                    Open 24 hours
                </span>
            ) : (
                <div className="flex items-center gap-2">
                    <HoursTimePicker
                        label={`${label} opens`}
                        value={draft.open_time}
                        onChange={(time) => onChange(withOpenTime(draft, time))}
                    />
                    <span className="text-sm text-gray-400">to</span>
                    <HoursTimePicker
                        label={`${label} closes`}
                        value={draft.close_time}
                        min={draft.open_time}
                        allowEndOfDay
                        invalid={error !== null}
                        onChange={(time) => onChange({ close_time: time })}
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
                                ? { open_time: '00:00', close_time: END_OF_DAY }
                                : { open_time: '07:00', close_time: '17:00' },
                        )
                    }
                />
                24 hours
            </label>
        </div>
    );
}

/**
 * One feature's week, kept small: chips for the open days and one pair of times for all of them,
 * with different hours per day one click away. The location's Hours pages have the full editor.
 */
function WeekEditor({
    title,
    weekKey,
    days,
    errors,
    onChange,
}: {
    title: string;
    weekKey: WeekKey;
    days: DayDraft[];
    errors: Record<string, string | undefined>;
    onChange: (days: DayDraft[]) => void;
}) {
    const [perDay, setPerDay] = useState(() => !sharesHours(days));
    const firstOpen =
        WEEK_ORDER.map((day) => days[day]).find((day) => day.is_open) ??
        days[1];
    const errorOf = (day: number) =>
        dayError(days[day]) ??
        errors[`${weekKey}.${day}.close_time`] ??
        errors[`${weekKey}.${day}.open_time`] ??
        null;
    const sharedError =
        WEEK_ORDER.map(errorOf).find((error) => error !== null) ?? null;

    const toggleDay = (day: number) =>
        onChange(
            days.map((draft, index) =>
                index === day ? { ...draft, is_open: !draft.is_open } : draft,
            ),
        );

    const setDay = (day: number, changes: Partial<DayDraft>) =>
        onChange(
            days.map((draft, index) =>
                index === day ? { ...draft, ...changes } : draft,
            ),
        );

    // Closed days take the times too, so opening one later matches the rest.
    const setAll = (changes: Partial<DayDraft>) =>
        onChange(days.map((draft) => ({ ...draft, ...changes })));

    const applySharedHours = () => {
        setAll({
            open_time: firstOpen.open_time,
            close_time: firstOpen.close_time,
        });
        setPerDay(false);
    };

    return (
        <section className="space-y-4 rounded-lg border border-gray-200 p-4 dark:border-gray-700/60">
            <div>
                <h3 className="text-sm font-semibold text-gray-800 dark:text-gray-100">
                    {title}
                </h3>
                <p
                    aria-live="polite"
                    className="mt-0.5 text-xs text-gray-500 dark:text-gray-400"
                >
                    {summarizeWeek(hoursFrom(days)).join(' · ')}
                </p>
            </div>

            <div>
                <div className="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                    Open on
                </div>
                <div className="flex flex-wrap gap-1.5">
                    {WEEK_ORDER.map((day) => (
                        <button
                            key={day}
                            type="button"
                            aria-pressed={days[day].is_open}
                            aria-label={DAY_NAMES[day]}
                            onClick={() => toggleDay(day)}
                            className={`h-9 w-11 cursor-pointer rounded-lg text-xs font-semibold transition ${
                                days[day].is_open
                                    ? 'bg-brand-green text-white hover:bg-brand-green/90'
                                    : 'border border-gray-200 bg-white text-gray-500 hover:border-gray-300 dark:border-gray-700/60 dark:bg-gray-800 dark:text-gray-400'
                            }`}
                        >
                            {DAY_NAMES[day].slice(0, 3)}
                        </button>
                    ))}
                </div>
            </div>

            {days.some((day) => day.is_open) && (
                <div>
                    <div className="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                        Hours
                    </div>
                    {perDay ? (
                        <ul className="space-y-2">
                            {WEEK_ORDER.filter((day) => days[day].is_open).map(
                                (day) => (
                                    <li
                                        key={day}
                                        className="flex flex-wrap items-center gap-x-3 gap-y-1"
                                    >
                                        <span className="w-10 text-sm font-medium text-gray-700 dark:text-gray-200">
                                            {DAY_NAMES[day].slice(0, 3)}
                                        </span>
                                        <TimeRange
                                            label={DAY_NAMES[day]}
                                            draft={days[day]}
                                            error={errorOf(day)}
                                            onChange={(changes) =>
                                                setDay(day, changes)
                                            }
                                        />
                                        {errorOf(day) && (
                                            <p
                                                role="alert"
                                                className="w-full pl-13 text-xs font-medium text-red-500"
                                            >
                                                {errorOf(day)}
                                            </p>
                                        )}
                                    </li>
                                ),
                            )}
                        </ul>
                    ) : (
                        <>
                            <TimeRange
                                label="Every open day"
                                draft={firstOpen}
                                error={sharedError}
                                onChange={setAll}
                            />
                            {sharedError && (
                                <p
                                    role="alert"
                                    className="mt-1.5 text-xs font-medium text-red-500"
                                >
                                    {sharedError}
                                </p>
                            )}
                        </>
                    )}
                    <button
                        type="button"
                        onClick={() =>
                            perDay ? applySharedHours() : setPerDay(true)
                        }
                        className="mt-3 cursor-pointer text-xs font-medium text-brand-green hover:underline"
                    >
                        {perDay
                            ? 'Use the same hours every day'
                            : 'Set different hours per day'}
                    </button>
                </div>
            )}
        </section>
    );
}

export default function HoursStep({
    features,
    draft,
    defaultHours,
}: {
    features: NonNullable<LocationWizardDraft['features']>;
    draft: LocationWizardDraft['hours'];
    defaultHours: DayDraft[];
}) {
    const both =
        features.is_checkins_enabled && features.is_appointments_enabled;
    const startFrom = (
        saved: LocationWizardHoursDay[] | undefined,
    ): DayDraft[] =>
        (saved ?? defaultHours).map((day, index) => ({
            is_open: day.is_open,
            // A closed day comes back from the draft without times; give it some to restore.
            open_time: day.open_time ?? defaultHours[index].open_time,
            close_time: day.close_time ?? defaultHours[index].close_time,
        }));

    const form = useForm<{ checkins: DayDraft[]; appointments: DayDraft[] }>({
        checkins: startFrom(draft?.checkins),
        appointments: startFrom(draft?.appointments),
    });
    const [sameAsCheckIns, setSameAsCheckIns] = useState(
        both && sameWeek(form.data.checkins, form.data.appointments),
    );

    const sharesWeek = both && sameAsCheckIns;
    const weeks: WeekKey[] = [
        ...(features.is_checkins_enabled ? (['checkins'] as const) : []),
        ...(features.is_appointments_enabled && !sharesWeek
            ? (['appointments'] as const)
            : []),
    ];
    const hasErrors = weeks.some((key) =>
        form.data[key].some((day) => dayError(day) !== null),
    );
    const errors = form.errors as Record<string, string | undefined>;

    const setWeek = (key: WeekKey, days: DayDraft[]) => {
        form.setData(key, days);
        form.clearErrors();
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (hasErrors) {
            return;
        }

        form.transform((data) => ({
            ...(features.is_checkins_enabled && {
                checkins: payloadOf(data.checkins),
            }),
            ...(features.is_appointments_enabled && {
                appointments: payloadOf(
                    sharesWeek ? data.checkins : data.appointments,
                ),
            }),
        }));
        form.submit(hours(), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} noValidate className="space-y-5">
            {features.is_checkins_enabled && (
                <WeekEditor
                    title={both ? 'Check-in hours' : 'Hours'}
                    weekKey="checkins"
                    days={form.data.checkins}
                    errors={errors}
                    onChange={(days) => setWeek('checkins', days)}
                />
            )}

            {both && (
                <label className="flex cursor-pointer items-center justify-between gap-4 rounded-lg bg-gray-50 px-4 py-3 dark:bg-gray-900/30">
                    <span>
                        <span className="block text-sm font-medium text-gray-800 dark:text-gray-100">
                            Same hours for appointments
                        </span>
                        <span className="block text-xs text-gray-500 dark:text-gray-400">
                            Turn off to give appointments their own hours.
                        </span>
                    </span>
                    <Switch
                        checked={sameAsCheckIns}
                        onCheckedChange={setSameAsCheckIns}
                    />
                </label>
            )}

            {features.is_appointments_enabled && !sharesWeek && (
                <WeekEditor
                    title={both ? 'Appointment hours' : 'Hours'}
                    weekKey="appointments"
                    days={form.data.appointments}
                    errors={errors}
                    onChange={(days) => setWeek('appointments', days)}
                />
            )}

            <p className="text-xs text-gray-500 dark:text-gray-400">
                Closures and special hours for holidays can be added on the
                location's pages afterwards.
            </p>

            <WizardFooter
                previous="features"
                processing={form.processing}
                disabled={hasErrors}
            />
        </form>
    );
}
