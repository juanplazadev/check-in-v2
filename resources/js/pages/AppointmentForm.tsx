import { zodResolver } from '@hookform/resolvers/zod';
import { Head, router, usePage } from '@inertiajs/react';
import { format } from 'date-fns';
import { UserIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { Controller, useFieldArray, useForm, useWatch } from 'react-hook-form';
import { z } from 'zod';

import AlertBanner from '@/components/AlertBanner';
import {
    DatePicker,
    TimePicker,
    buildSlots,
    minutesToValue,
    timeToMinutes,
} from '@/components/DatePickerTime';
import LocationScheduleCard from '@/components/LocationScheduleCard';
import { PhoneInput, formatUsPhone } from '@/components/PhoneInput';
import PurchaseOrderInputs, {
    poNumberSchema,
} from '@/components/PurchaseOrderInputs';
import type { PurchaseOrderTranslations } from '@/components/PurchaseOrderInputs';
import SectionHeader from '@/components/SectionHeader';
import { Button } from '@/components/ui/button';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
    FieldDescription,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import PublicLayout from '@/layouts/PublicLayout';
import { formatLongDate, formatTimeOfDay } from '@/lib/locale';
import { cn } from '@/lib/utils';

// ── Types ────────────────────────────────────────────────────────────────────

interface AvailabilityWindow {
    date: string;
    firstSlot: string;
    lastSlot: string;
}

interface Location {
    id: string;
    name: string;
    address: string;
    isOpen: boolean;
    todayOpenCloseTime: string | null;
    reason: string | null;
    hasOverride: boolean;
    isOverrideClosure: boolean;
    isClosingSoon: boolean | null;
    availability: AvailabilityWindow[];
    slotIntervalMinutes: number;
}

/** Mirrors the loadTypes prop built in AppointmentController::form(). */
interface LoadTypeOption {
    id: string;
    name: string;
    direction: 'inbound' | 'outbound';
    directionLabel: string;
    durationMinutes: number;
}

interface AppointmentFormTranslations {
    pageTitle: string;
    newAppointment: string;
    appointmentDetails: string;
    driverInformation: string;
    dateLabel: string;
    dateRequired: string;
    timeLabel: string;
    timeRequired: string;
    driversNameLabel: string;
    driversNamePlaceholder: string;
    driversNameMin: string;
    driversNameMax: string;
    cellphoneLabel: string;
    cellphonePlaceholder: string;
    cellphoneInvalid: string;
    cellphoneConsent: string;
    todaysHours: string;
    noAvailability: string;
    slotFull: string;
    reviewButton: string;
    submitButton: string;
    submitting: string;
    reviewNote: string;
    reviewTitle: string;
    editButton: string;
    open: string;
    loadTypeLabel: string;
    loadTypeHint: string;
    loadTypeRequired: string;
    durationMinutes: string;
    durationHours: string;
    durationHoursMinutes: string;
    noTimesForLoad: string;
    selectDate: string;
    morning: string;
    afternoon: string;
    evening: string;
}

interface Translations {
    appointmentForm: AppointmentFormTranslations;
    purchaseOrders: PurchaseOrderTranslations;
    publicNavigation: { close: string; openInMaps: string };
}

/** Start times with no room left, by booking length and local date: { '90': { '2026-11-02': ['08:00:00'] } }. */
type FullSlots = Record<string, Record<string, string[]>>;

interface PageProps {
    location: Location;
    /** Empty when the location has no load types; every booking then lasts one slot. */
    loadTypes: LoadTypeOption[];
    /** False when staff set the load type: loadTypes then holds that one, preselected, and no step is shown. */
    chooseLoadType: boolean;
    /** When the location closes on each available day, "HH:MM:SS" by local date. */
    closingTimes: Record<string, string>;
    fullSlots: FullSlots;
    translations: Translations;
    currentLocale: string;
    auth: { user: { name: string; cellphone: string | null } | null };
    [key: string]: unknown;
}

/** Replaces :name placeholders, the way Laravel's __() does on the server. */
function interpolate(text: string, values: Record<string, string | number>) {
    return Object.entries(values).reduce(
        (result, [key, value]) => result.replaceAll(`:${key}`, String(value)),
        text,
    );
}

/** "45 min", "1 h", "1 h 30 min", in the page's language. */
function formatDuration(
    minutes: number,
    t: AppointmentFormTranslations,
): string {
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;

    if (hours === 0) {
        return interpolate(t.durationMinutes, { minutes: rest });
    }

    return rest === 0
        ? interpolate(t.durationHours, { hours })
        : interpolate(t.durationHoursMinutes, { hours, minutes: rest });
}

/**
 * A day's window cut down so a booking of this length still finishes by
 * closing time, or undefined when no start time fits at all.
 */
function fitWindow(
    window: AvailabilityWindow,
    durationMinutes: number,
    closingTimes: Record<string, string>,
): AvailabilityWindow | undefined {
    const closesAt = closingTimes[window.date];
    const latestStart =
        closesAt === undefined
            ? timeToMinutes(window.lastSlot)
            : Math.min(
                  timeToMinutes(window.lastSlot),
                  timeToMinutes(closesAt) - durationMinutes,
              );

    if (latestStart < timeToMinutes(window.firstSlot)) {
        return undefined;
    }

    return { ...window, lastSlot: minutesToValue(latestStart) };
}

/** The first slot in a day's window that still has room. */
function firstOpenSlot(
    window: AvailabilityWindow,
    intervalMinutes: number,
    fullSlots: string[],
): string | undefined {
    const full = new Set(fullSlots);

    return buildSlots(window.firstSlot, window.lastSlot, intervalMinutes).find(
        (slot) => !full.has(slot),
    );
}

/**
 * The days a booking of this length can be made, each cut to the start times
 * that fit. A day whose every slot is full is left out, as if it had no hours.
 */
function windowsFor(
    availability: AvailabilityWindow[],
    durationMinutes: number | null,
    intervalMinutes: number,
    closingTimes: Record<string, string>,
    fullSlots: FullSlots,
): AvailabilityWindow[] {
    if (durationMinutes === null) {
        return [];
    }

    const full = fullSlots[String(durationMinutes)] ?? {};

    return availability
        .map((w) => fitWindow(w, durationMinutes, closingTimes))
        .filter(
            (w): w is AvailabilityWindow =>
                w !== undefined &&
                firstOpenSlot(w, intervalMinutes, full[w.date] ?? []) !==
                    undefined,
        );
}

/** "9:00 AM - 10:30 AM", in the page's language. */
function formatTimeRange(
    time: string,
    durationMinutes: number,
    locale: string,
): string {
    const end = minutesToValue(timeToMinutes(time) + durationMinutes);

    return `${formatTimeOfDay(time, locale)} - ${formatTimeOfDay(end, locale)}`;
}

// ── Review summary ──────────────────────────────────────────────────────────

function ReviewSummary({
    location,
    values,
    loadType,
    durationMinutes,
    locale,
    t,
    poTranslations,
}: {
    location: Location;
    values: {
        date: string;
        time: string;
        po_numbers: { value: string }[];
        drivers_name: string;
        drivers_cellphone: string;
    };
    loadType: LoadTypeOption | undefined;
    durationMinutes: number;
    locale: string;
    t: AppointmentFormTranslations;
    poTranslations: PurchaseOrderTranslations;
}) {
    const formattedDate = values.date
        ? formatLongDate(values.date, locale)
        : '';

    const formattedTime = values.time
        ? formatTimeRange(values.time, durationMinutes, locale)
        : '';

    return (
        <div className="space-y-5 px-5 pt-5 pb-6">
            <SectionHeader step={1} label={t.reviewTitle} />

            <div className="space-y-4">
                <div className="border-gray-150/50 overflow-hidden rounded-lg border bg-gray-100/50 dark:border-gray-900/50 dark:bg-gray-950/30">
                    <dl className="divide-y divide-gray-200 dark:divide-gray-700/60">
                        <div className="flex justify-between gap-4 px-4 py-3">
                            <dt className="text-xs font-medium text-gray-500 dark:text-gray-400">
                                {t.appointmentDetails}
                            </dt>
                            <dd className="text-right text-sm font-semibold text-gray-900 dark:text-gray-100">
                                {location.name}
                            </dd>
                        </div>
                        <div className="flex justify-between gap-4 px-4 py-3">
                            <dt className="text-xs font-medium text-gray-500 dark:text-gray-400">
                                {t.dateLabel}
                            </dt>
                            <dd className="text-sm text-gray-900 dark:text-gray-100">
                                {formattedDate}
                            </dd>
                        </div>
                        <div className="flex justify-between gap-4 px-4 py-3">
                            <dt className="text-xs font-medium text-gray-500 dark:text-gray-400">
                                {t.timeLabel}
                            </dt>
                            <dd className="text-sm text-gray-900 dark:text-gray-100">
                                {formattedTime}
                            </dd>
                        </div>
                        {loadType && (
                            <div className="flex justify-between gap-4 px-4 py-3">
                                <dt className="text-xs font-medium text-gray-500 dark:text-gray-400">
                                    {t.loadTypeLabel}
                                </dt>
                                <dd className="text-right text-sm text-gray-900 dark:text-gray-100">
                                    {loadType.name}
                                </dd>
                            </div>
                        )}
                        <div className="flex justify-between gap-4 px-4 py-3">
                            <dt className="text-xs font-medium text-gray-500 dark:text-gray-400">
                                {poTranslations.label}
                            </dt>
                            <dd className="text-right text-sm text-gray-900 dark:text-gray-100">
                                {values.po_numbers
                                    .map((po) => po.value)
                                    .join(', ')}
                            </dd>
                        </div>
                        <div className="flex justify-between gap-4 px-4 py-3">
                            <dt className="text-xs font-medium text-gray-500 dark:text-gray-400">
                                {t.driversNameLabel}
                            </dt>
                            <dd className="text-sm text-gray-900 dark:text-gray-100">
                                {values.drivers_name}
                            </dd>
                        </div>
                        <div className="flex justify-between gap-4 px-4 py-3">
                            <dt className="text-xs font-medium text-gray-500 dark:text-gray-400">
                                {t.cellphoneLabel}
                            </dt>
                            <dd className="text-sm text-gray-900 dark:text-gray-100">
                                +1 {values.drivers_cellphone}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    );
}

// ── Load type picker ────────────────────────────────────────────────────────

function LoadTypeGroup({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <div>
            <p className="mb-2 text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                {label}
            </p>
            <div className="grid gap-2 sm:grid-cols-2">{children}</div>
        </div>
    );
}

function LoadTypeChoice({
    name,
    duration,
    selected,
    onSelect,
}: {
    name: string;
    duration: string;
    selected: boolean;
    onSelect: () => void;
}) {
    return (
        <button
            type="button"
            role="radio"
            aria-checked={selected}
            onClick={onSelect}
            className={cn(
                'flex min-h-12 cursor-pointer flex-col items-start justify-center rounded-md border px-3 py-2 text-left transition-colors',
                selected
                    ? 'border-brand-green bg-brand-green/10 ring-1 ring-brand-green'
                    : 'border-input hover:bg-accent hover:text-accent-foreground',
            )}
        >
            <span className="text-sm font-medium text-gray-900 dark:text-gray-100">
                {name}
            </span>
            <span className="text-xs text-muted-foreground tabular-nums">
                {duration}
            </span>
        </button>
    );
}

// ── Page ─────────────────────────────────────────────────────────────────────

export default function ScheduleAppointment({
    location,
    loadTypes,
    chooseLoadType,
    closingTimes,
    fullSlots,
}: PageProps) {
    const { translations, auth, currentLocale } = usePage<PageProps>().props;
    const t = translations.appointmentForm;
    const poT = translations.purchaseOrders;
    const hasLoadTypes = loadTypes.length > 0;
    const interval = location.slotIntervalMinutes;

    /** How long a booking holds a dock; null until a driver picks a load type. */
    const durationFor = (loadTypeId: string): number | null => {
        if (!hasLoadTypes) {
            return interval;
        }

        return (
            loadTypes.find((loadType) => loadType.id === loadTypeId)
                ?.durationMinutes ?? null
        );
    };

    const windowsForLoadType = (loadTypeId: string) =>
        windowsFor(
            location.availability ?? [],
            durationFor(loadTypeId),
            interval,
            closingTimes,
            fullSlots,
        );

    const fullFor = (loadTypeId: string, date: string): string[] =>
        fullSlots[String(durationFor(loadTypeId))]?.[date] ?? [];

    // A single load type needs no choice, so it starts selected.
    const initialLoadType = loadTypes.length === 1 ? loadTypes[0].id : '';
    const initialWindow = windowsForLoadType(initialLoadType)[0];

    // Prefill the driver's identity for signed-in drivers, from their saved
    // profile. Guests start with blank fields.
    const prefillName = auth.user?.name ?? '';
    const prefillCellphone = auth.user?.cellphone
        ? formatUsPhone(auth.user.cellphone.replace(/^\+1/, ''))
        : '';

    const [processing, setProcessing] = useState(false);
    const [step, setStep] = useState<'form' | 'review'>('form');
    const [serverError, setServerError] = useState<string | null>(null);

    const schema = useMemo(
        () =>
            z.object({
                location_id: z.string(),
                load_type: hasLoadTypes
                    ? z.string().min(1, t.loadTypeRequired)
                    : z.string(),
                date: z.string().min(1, t.dateRequired),
                time: z.string().min(1, t.timeRequired),
                po_numbers: poNumberSchema(poT),
                drivers_name: z
                    .string()
                    .min(2, t.driversNameMin)
                    .max(28, t.driversNameMax),
                drivers_cellphone: z
                    .string()
                    .regex(/^\(\d{3}\) \d{3}-\d{4}$/, t.cellphoneInvalid),
            }),
        [t, poT, hasLoadTypes],
    );

    type FormValues = z.infer<typeof schema>;

    const { control, handleSubmit, setError, setValue, getValues } =
        useForm<FormValues>({
            resolver: zodResolver(schema),
            defaultValues: {
                location_id: location.id,
                load_type: initialLoadType,
                date: initialWindow?.date ?? '',
                time: initialWindow
                    ? (firstOpenSlot(
                          initialWindow,
                          interval,
                          fullFor(initialLoadType, initialWindow.date),
                      ) ?? initialWindow.firstSlot)
                    : '10:00:00',
                po_numbers: [{ value: '' }],
                drivers_name: prefillName,
                drivers_cellphone: prefillCellphone,
            },
        });

    const { fields, append, remove } = useFieldArray({
        control,
        name: 'po_numbers',
    });

    const selectedDate = useWatch({ control, name: 'date' });
    const selectedLoadTypeId = useWatch({ control, name: 'load_type' });
    const selectedLoadType = loadTypes.find(
        (loadType) => loadType.id === selectedLoadTypeId,
    );
    const durationMinutes = durationFor(selectedLoadTypeId);

    // Each load type has its own open times: a long load needs room for
    // longer and has to finish by closing.
    const availability = windowsForLoadType(selectedLoadTypeId);
    const isOpenForBookings = (location.availability ?? []).length > 0;
    const hasAvailability = availability.length > 0;

    const availableDates = new Set(availability.map((w) => w.date));
    const windowByDate = new Map(availability.map((w) => [w.date, w]));
    const currentWindow = selectedDate
        ? windowByDate.get(selectedDate)
        : undefined;

    /** Moves the date and time to the first open ones when they no longer fit. */
    const selectLoadType = (loadTypeId: string) => {
        setValue('load_type', loadTypeId, { shouldValidate: true });

        const windows = windowsForLoadType(loadTypeId);
        const { date, time } = getValues();
        const window =
            windows.find((w) => w.date === date) ?? windows[0] ?? undefined;

        if (!window) {
            setValue('date', '');

            return;
        }

        const full = new Set(fullFor(loadTypeId, window.date));
        const slots = buildSlots(window.firstSlot, window.lastSlot, interval);
        const stillFits =
            window.date === date && slots.includes(time) && !full.has(time);

        if (!stillFits) {
            setValue('date', window.date);
            setValue(
                'time',
                firstOpenSlot(window, interval, [...full]) ?? window.firstSlot,
            );
        }
    };

    const loadTypeGroups = (['inbound', 'outbound'] as const)
        .map((direction) => ({
            direction,
            items: loadTypes.filter(
                (loadType) => loadType.direction === direction,
            ),
        }))
        .filter((group) => group.items.length > 0);

    const onReview = () => {
        handleSubmit(() => setStep('review'))();
    };

    const formFieldKeys: Array<keyof FormValues> = [
        'load_type',
        'drivers_name',
        'drivers_cellphone',
        'date',
        'time',
    ];

    const onSubmit = () => {
        const values = getValues();
        setProcessing(true);
        setServerError(null);

        const payload = {
            datetime: `${values.date} ${values.time}`,
            load_type: values.load_type === '' ? null : values.load_type,
            po_numbers: values.po_numbers.map((po) => po.value),
            drivers_name: values.drivers_name,
            drivers_cellphone: values.drivers_cellphone,
        };

        router.post(`/appointment/${location.id}/book`, payload, {
            onError: (backendErrors) => {
                setStep('form');
                const unmappedErrors: string[] = [];

                Object.entries(backendErrors).forEach(([key, message]) => {
                    const poMatch = key.match(/^po_numbers\.(\d+)$/);

                    if (poMatch) {
                        setError(`po_numbers.${poMatch[1]}.value` as any, {
                            type: 'server',
                            message,
                        });
                    } else if (
                        formFieldKeys.includes(key as keyof FormValues)
                    ) {
                        setError(key as keyof FormValues, {
                            type: 'server',
                            message,
                        });
                    } else {
                        unmappedErrors.push(message);
                    }
                });

                if (unmappedErrors.length > 0) {
                    setServerError(unmappedErrors.join(' '));
                }
            },
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <PublicLayout>
            <Head title={t.pageTitle} />

            <div className="px-4 py-6 sm:py-10">
                <div className="mx-auto max-w-md lg:max-w-xl">
                    {/* Page title */}
                    <div className="mb-6">
                        <p className="mb-1 text-[10px] font-bold tracking-widest text-brand-green uppercase">
                            {t.newAppointment}
                        </p>
                        <h1 className="text-2xl font-bold text-brand-grey dark:text-gray-100">
                            {t.pageTitle}
                        </h1>
                    </div>

                    {/* Card */}
                    <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700/60 dark:bg-gray-800">
                        {/* Green accent bar at top */}
                        <div className="h-1 w-full bg-brand-green" />

                        {step === 'form' ? (
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    onReview();
                                }}
                                noValidate
                            >
                                {/* ── Section 1: Appointment Details ───────── */}
                                <div className="px-5 pt-5 pb-6">
                                    <SectionHeader
                                        step={1}
                                        label={t.appointmentDetails}
                                    />

                                    {serverError && (
                                        <div className="mt-4">
                                            <AlertBanner
                                                type="error"
                                                open={!!serverError}
                                                onClose={() =>
                                                    setServerError(null)
                                                }
                                                closeLabel={
                                                    translations
                                                        .publicNavigation.close
                                                }
                                            >
                                                {serverError}
                                            </AlertBanner>
                                        </div>
                                    )}

                                    {/* Location + today's hours */}
                                    <div className="mt-4">
                                        <LocationScheduleCard
                                            location={location}
                                            todaysHoursLabel={t.todaysHours}
                                            openLabel={t.open}
                                            openInMapsLabel={
                                                translations.publicNavigation
                                                    .openInMaps
                                            }
                                        />
                                    </div>

                                    {/* Load type */}
                                    {chooseLoadType && isOpenForBookings && (
                                        <div className="mt-5">
                                            <Controller
                                                name="load_type"
                                                control={control}
                                                render={({
                                                    field,
                                                    fieldState,
                                                }) => (
                                                    <Field
                                                        data-invalid={
                                                            fieldState.invalid
                                                        }
                                                    >
                                                        <FieldLabel>
                                                            {t.loadTypeLabel}
                                                        </FieldLabel>
                                                        <FieldDescription>
                                                            {t.loadTypeHint}
                                                        </FieldDescription>
                                                        <div
                                                            role="radiogroup"
                                                            aria-label={
                                                                t.loadTypeLabel
                                                            }
                                                            className="space-y-3"
                                                        >
                                                            {loadTypeGroups.map(
                                                                (group) => (
                                                                    <LoadTypeGroup
                                                                        key={
                                                                            group.direction
                                                                        }
                                                                        label={
                                                                            group
                                                                                .items[0]
                                                                                .directionLabel
                                                                        }
                                                                    >
                                                                        {group.items.map(
                                                                            (
                                                                                loadType,
                                                                            ) => (
                                                                                <LoadTypeChoice
                                                                                    key={
                                                                                        loadType.id
                                                                                    }
                                                                                    name={
                                                                                        loadType.name
                                                                                    }
                                                                                    duration={formatDuration(
                                                                                        loadType.durationMinutes,
                                                                                        t,
                                                                                    )}
                                                                                    selected={
                                                                                        field.value ===
                                                                                        loadType.id
                                                                                    }
                                                                                    onSelect={() =>
                                                                                        selectLoadType(
                                                                                            loadType.id,
                                                                                        )
                                                                                    }
                                                                                />
                                                                            ),
                                                                        )}
                                                                    </LoadTypeGroup>
                                                                ),
                                                            )}
                                                        </div>
                                                        {fieldState.invalid && (
                                                            <FieldError
                                                                errors={[
                                                                    fieldState.error,
                                                                ]}
                                                            />
                                                        )}
                                                    </Field>
                                                )}
                                            />
                                        </div>
                                    )}

                                    {/* Date + Time */}
                                    {/* Hidden until a load type is picked, unless the location is closed for booking anyway. */}
                                    {durationMinutes === null &&
                                    isOpenForBookings ? null : hasAvailability ? (
                                        <div className="mt-5 space-y-5">
                                            <Controller
                                                name="date"
                                                control={control}
                                                render={({
                                                    field,
                                                    fieldState,
                                                }) => (
                                                    <Field
                                                        data-invalid={
                                                            fieldState.invalid
                                                        }
                                                    >
                                                        <FieldLabel htmlFor="date">
                                                            {t.dateLabel}
                                                        </FieldLabel>
                                                        <DatePicker
                                                            availableDates={
                                                                availableDates
                                                            }
                                                            placeholder={
                                                                t.selectDate
                                                            }
                                                            locale={
                                                                currentLocale
                                                            }
                                                            date={
                                                                field.value
                                                                    ? new Date(
                                                                          field.value +
                                                                              'T00:00:00',
                                                                      )
                                                                    : undefined
                                                            }
                                                            onDateChange={(
                                                                d,
                                                            ) => {
                                                                const ds = d
                                                                    ? format(
                                                                          d,
                                                                          'yyyy-MM-dd',
                                                                      )
                                                                    : '';
                                                                field.onChange(
                                                                    ds,
                                                                );
                                                                const w =
                                                                    windowByDate.get(
                                                                        ds,
                                                                    );

                                                                if (w) {
                                                                    setValue(
                                                                        'time',
                                                                        firstOpenSlot(
                                                                            w,
                                                                            interval,
                                                                            fullFor(
                                                                                selectedLoadTypeId,
                                                                                ds,
                                                                            ),
                                                                        ) ??
                                                                            w.firstSlot,
                                                                        {
                                                                            shouldValidate: true,
                                                                        },
                                                                    );
                                                                }
                                                            }}
                                                        />
                                                        {fieldState.invalid && (
                                                            <FieldError
                                                                errors={[
                                                                    fieldState.error,
                                                                ]}
                                                            />
                                                        )}
                                                    </Field>
                                                )}
                                            />

                                            <Controller
                                                name="time"
                                                control={control}
                                                render={({
                                                    field,
                                                    fieldState,
                                                }) => (
                                                    <Field
                                                        data-invalid={
                                                            fieldState.invalid
                                                        }
                                                    >
                                                        <FieldLabel>
                                                            {t.timeLabel}
                                                        </FieldLabel>
                                                        <TimePicker
                                                            value={field.value}
                                                            onChange={
                                                                field.onChange
                                                            }
                                                            firstSlot={
                                                                currentWindow?.firstSlot
                                                            }
                                                            lastSlot={
                                                                currentWindow?.lastSlot
                                                            }
                                                            intervalMinutes={
                                                                interval
                                                            }
                                                            fullSlots={
                                                                selectedDate
                                                                    ? fullFor(
                                                                          selectedLoadTypeId,
                                                                          selectedDate,
                                                                      )
                                                                    : undefined
                                                            }
                                                            fullLabel={
                                                                t.slotFull
                                                            }
                                                            groupLabels={{
                                                                morning:
                                                                    t.morning,
                                                                afternoon:
                                                                    t.afternoon,
                                                                evening:
                                                                    t.evening,
                                                            }}
                                                            locale={
                                                                currentLocale
                                                            }
                                                        />
                                                        {fieldState.invalid && (
                                                            <FieldError
                                                                errors={[
                                                                    fieldState.error,
                                                                ]}
                                                            />
                                                        )}
                                                    </Field>
                                                )}
                                            />
                                        </div>
                                    ) : (
                                        <div className="mt-5 rounded-lg border border-amber-300/60 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
                                            {isOpenForBookings && chooseLoadType
                                                ? t.noTimesForLoad
                                                : t.noAvailability}
                                        </div>
                                    )}

                                    {/* PO Numbers */}
                                    <div className="mt-4">
                                        <PurchaseOrderInputs
                                            control={control}
                                            fields={fields}
                                            append={append}
                                            remove={remove}
                                            translations={poT}
                                        />
                                    </div>
                                </div>

                                {/* Divider */}
                                <div className="mx-5 border-t border-dashed border-gray-200 dark:border-gray-700" />

                                {/* ── Section 2: Driver Information ─────────── */}
                                <div className="px-5 pt-5 pb-5">
                                    <SectionHeader
                                        step={2}
                                        label={t.driverInformation}
                                    />

                                    <FieldGroup className="mt-4">
                                        {/* Driver Name */}
                                        <Controller
                                            name="drivers_name"
                                            control={control}
                                            render={({ field, fieldState }) => (
                                                <Field
                                                    data-invalid={
                                                        fieldState.invalid
                                                    }
                                                >
                                                    <FieldLabel htmlFor="drivers_name">
                                                        {t.driversNameLabel}
                                                    </FieldLabel>
                                                    <div className="relative">
                                                        <UserIcon className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                                        <Input
                                                            {...field}
                                                            id="drivers_name"
                                                            maxLength={28}
                                                            aria-invalid={
                                                                fieldState.invalid
                                                            }
                                                            placeholder={
                                                                t.driversNamePlaceholder
                                                            }
                                                            className="pl-9"
                                                        />
                                                    </div>
                                                    {fieldState.invalid && (
                                                        <FieldError
                                                            errors={[
                                                                fieldState.error,
                                                            ]}
                                                        />
                                                    )}
                                                </Field>
                                            )}
                                        />

                                        {/* Cellphone */}
                                        <Controller
                                            name="drivers_cellphone"
                                            control={control}
                                            render={({ field, fieldState }) => (
                                                <Field
                                                    data-invalid={
                                                        fieldState.invalid
                                                    }
                                                >
                                                    <FieldLabel htmlFor="drivers_cellphone">
                                                        {t.cellphoneLabel}
                                                    </FieldLabel>
                                                    <PhoneInput
                                                        id="drivers_cellphone"
                                                        name={field.name}
                                                        value={field.value}
                                                        onChange={
                                                            field.onChange
                                                        }
                                                        onBlur={field.onBlur}
                                                        ref={field.ref}
                                                        invalid={
                                                            fieldState.invalid
                                                        }
                                                        placeholder={
                                                            t.cellphonePlaceholder
                                                        }
                                                    />
                                                    <FieldDescription>
                                                        {t.cellphoneConsent}
                                                    </FieldDescription>
                                                    {fieldState.invalid && (
                                                        <FieldError
                                                            errors={[
                                                                fieldState.error,
                                                            ]}
                                                        />
                                                    )}
                                                </Field>
                                            )}
                                        />
                                    </FieldGroup>
                                </div>

                                {/* ── Submit footer ─────────────────────────── */}
                                <div className="border-t border-gray-100 bg-gray-50 px-5 py-4 dark:border-gray-700/60 dark:bg-gray-800/60">
                                    <Button
                                        type="submit"
                                        disabled={!isOpenForBookings}
                                        className="w-full cursor-pointer bg-brand-green text-white hover:bg-brand-green/90 focus-visible:ring-brand-green/50"
                                    >
                                        {t.reviewButton}
                                    </Button>
                                    <p className="mt-2.5 text-center text-[11px] text-gray-400 dark:text-gray-500">
                                        {t.reviewNote}
                                    </p>
                                </div>
                            </form>
                        ) : (
                            <div>
                                <ReviewSummary
                                    location={location}
                                    values={getValues()}
                                    loadType={
                                        chooseLoadType
                                            ? selectedLoadType
                                            : undefined
                                    }
                                    durationMinutes={
                                        durationMinutes ?? interval
                                    }
                                    locale={currentLocale}
                                    t={t}
                                    poTranslations={poT}
                                />

                                {/* ── Review footer ──────────────────────── */}
                                <div className="border-t border-gray-100 bg-gray-50 px-5 py-4 dark:border-gray-700/60 dark:bg-gray-800/60">
                                    <div className="flex gap-3">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="flex-1 cursor-pointer"
                                            onClick={() => setStep('form')}
                                            disabled={processing}
                                        >
                                            {t.editButton}
                                        </Button>
                                        <Button
                                            type="button"
                                            className="flex-1 cursor-pointer bg-brand-green text-white hover:bg-brand-green/90 focus-visible:ring-brand-green/50"
                                            onClick={onSubmit}
                                            disabled={processing}
                                        >
                                            {processing
                                                ? t.submitting
                                                : t.submitButton}
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </PublicLayout>
    );
}
