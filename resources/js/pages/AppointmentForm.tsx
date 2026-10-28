import { zodResolver } from '@hookform/resolvers/zod';
import { Head, router, usePage } from '@inertiajs/react';
import { format } from 'date-fns';
import { UserIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Controller, useFieldArray, useForm, useWatch } from 'react-hook-form';
import { z } from 'zod';

import AlertBanner from '@/components/AlertBanner';
import {
    DatePicker,
    TimePicker,
    buildSlots,
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
}

interface Translations {
    appointmentForm: AppointmentFormTranslations;
    purchaseOrders: PurchaseOrderTranslations;
}

interface PageProps {
    location: Location;
    /** Slots with no room left, by local date: { '2026-11-02': ['08:00:00'] }. */
    fullSlots: Record<string, string[]>;
    translations: Translations;
    auth: { user: { name: string; cellphone: string | null } | null };
    [key: string]: unknown;
}

/** The first slot in a day's window that still has room. */
function firstOpenSlot(
    window: AvailabilityWindow,
    intervalMinutes: number,
    fullSlots: Record<string, string[]>,
): string | undefined {
    const full = new Set(fullSlots[window.date] ?? []);

    return buildSlots(window.firstSlot, window.lastSlot, intervalMinutes).find(
        (slot) => !full.has(slot),
    );
}

// ── Review summary ──────────────────────────────────────────────────────────

function ReviewSummary({
    location,
    values,
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
    t: AppointmentFormTranslations;
    poTranslations: PurchaseOrderTranslations;
}) {
    const formattedDate = values.date
        ? format(new Date(values.date + 'T00:00:00'), 'MMMM d, yyyy')
        : '';

    const formattedTime = values.time
        ? format(new Date(`2000-01-01T${values.time}`), 'h:mm a')
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

// ── Page ─────────────────────────────────────────────────────────────────────

export default function ScheduleAppointment({
    location,
    fullSlots,
}: PageProps) {
    const { translations, auth } = usePage<PageProps>().props;
    const t = translations.appointmentForm;
    const poT = translations.purchaseOrders;

    // Prefill the driver's identity for signed-in drivers, from their saved
    // profile. Guests start with blank fields.
    const prefillName = auth.user?.name ?? '';
    const prefillCellphone = auth.user?.cellphone
        ? formatUsPhone(auth.user.cellphone.replace(/^\+1/, ''))
        : '';

    const [processing, setProcessing] = useState(false);
    const [step, setStep] = useState<'form' | 'review'>('form');
    const [serverError, setServerError] = useState<string | null>(null);

    // A day whose every slot is full is left out, as if it had no hours.
    const availability = useMemo(
        () =>
            (location.availability ?? []).filter(
                (w) =>
                    firstOpenSlot(
                        w,
                        location.slotIntervalMinutes,
                        fullSlots,
                    ) !== undefined,
            ),
        [location.availability, location.slotIntervalMinutes, fullSlots],
    );
    const hasAvailability = availability.length > 0;

    const availableDates = useMemo(
        () => new Set(availability.map((w) => w.date)),
        [availability],
    );
    const windowByDate = useMemo(
        () => new Map(availability.map((w) => [w.date, w])),
        [availability],
    );

    const firstWindow = availability[0];

    const schema = useMemo(
        () =>
            z.object({
                location_id: z.string(),
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
        [t, poT],
    );

    type FormValues = z.infer<typeof schema>;

    const { control, handleSubmit, setError, setValue, getValues } =
        useForm<FormValues>({
            resolver: zodResolver(schema),
            defaultValues: {
                location_id: location.id,
                date: firstWindow?.date ?? '',
                time: firstWindow
                    ? (firstOpenSlot(
                          firstWindow,
                          location.slotIntervalMinutes,
                          fullSlots,
                      ) ?? firstWindow.firstSlot)
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
    const currentWindow = selectedDate
        ? windowByDate.get(selectedDate)
        : undefined;

    const onReview = () => {
        handleSubmit(() => setStep('review'))();
    };

    const formFieldKeys: Array<keyof FormValues> = [
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
                                        />
                                    </div>

                                    {/* Date + Time */}
                                    {hasAvailability ? (
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
                                                                            location.slotIntervalMinutes,
                                                                            fullSlots,
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
                                                                location.slotIntervalMinutes
                                                            }
                                                            fullSlots={
                                                                selectedDate
                                                                    ? fullSlots[
                                                                          selectedDate
                                                                      ]
                                                                    : undefined
                                                            }
                                                            fullLabel={
                                                                t.slotFull
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
                                            {t.noAvailability}
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
                                        disabled={!hasAvailability}
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
