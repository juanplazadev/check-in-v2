import { zodResolver } from '@hookform/resolvers/zod';
import { Head, router, usePage } from '@inertiajs/react';
import { format } from 'date-fns';
import { ChevronDownIcon, UserIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Controller, useFieldArray, useForm } from 'react-hook-form';
import { z } from 'zod';

import { store } from '@/actions/App/Http/Controllers/CheckInController';
import AlertBanner from '@/components/AlertBanner';
import CountryDisplay from '@/components/CountryDisplay';
import { DatePicker } from '@/components/DatePickerTime';
import LocationAutocomplete from '@/components/LocationAutocomplete';
import LocationScheduleCard from '@/components/LocationScheduleCard';
import PurchaseOrderInputs, {
    poNumberSchema,
} from '@/components/PurchaseOrderInputs';
import type { PurchaseOrderTranslations } from '@/components/PurchaseOrderInputs';
import SectionHeader from '@/components/SectionHeader';
import { Button } from '@/components/ui/button';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { PhoneInput, formatUsPhone } from '@/components/PhoneInput';
import { Textarea } from '@/components/ui/textarea';
import PublicLayout from '@/layouts/PublicLayout';
import { countryName } from '@/lib/countryFlag';

// ── Types ────────────────────────────────────────────────────────────────────

interface Location {
    id: string;
    name: string;
    address: string;
    maxDistanceAllowed: number;
    isOpen: boolean;
    todayOpenCloseTime: string | null;
    reason: string | null;
    hasOverride: boolean;
    isOverrideClosure: boolean;
    isClosingSoon: boolean | null;
}

interface FieldFlags {
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

interface CheckInFormTranslations {
    pageTitle: string;
    newCheckIn: string;
    checkInDetails: string;
    truckTrailer: string;
    driverInformation: string;
    todaysHours: string;
    open: string;
    customerLabel: string;
    customerPlaceholder: string;
    customerRequired: string;
    customerMin: string;
    customerMax: string;
    destinationCityLabel: string;
    destinationCityPlaceholder: string;
    destinationCityRequired: string;
    destinationCityMin: string;
    destinationStateLabel: string;
    destinationStatePlaceholder: string;
    destinationStateRequired: string;
    destinationStateMin: string;
    destinationCountryLabel: string;
    destinationCountryPlaceholder: string;
    destinationCountryRequired: string;
    destinationLabel: string;
    truckNameLabel: string;
    truckNamePlaceholder: string;
    truckNameRequired: string;
    truckNameMax: string;
    truckPlateLabel: string;
    platePlaceholder: string;
    truckPlateRequired: string;
    plateInvalid: string;
    truckColorLabel: string;
    truckColorPlaceholder: string;
    truckColorRequired: string;
    truckColors: Record<string, string>;
    truckPlateStateLabel: string;
    truckPlateCountryLabel: string;
    trailerPlateLabel: string;
    trailerPlateRequired: string;
    trailerPlateStateLabel: string;
    trailerPlateCountryLabel: string;
    statePlaceholder: string;
    stateRequired: string;
    stateMin: string;
    stateMax: string;
    countryCodePlaceholder: string;
    countryCodeRequired: string;
    countryCodeInvalid: string;
    trailerChuteLabel: string;
    trailerChuteNa: string;
    trailerChuteCenter: string;
    trailerChuteSide: string;
    emptyWeightLabel: string;
    emptyWeightPlaceholder: string;
    emptyWeightRequired: string;
    emptyWeightInvalid: string;
    driversNameLabel: string;
    driversNamePlaceholder: string;
    driversNameRequired: string;
    driversNameMin: string;
    driversNameMax: string;
    cellphoneLabel: string;
    cellphonePlaceholder: string;
    cellphoneRequired: string;
    cellphoneInvalid: string;
    cellphoneConsent: string;
    loadingInstructionsLabel: string;
    loadingInstructionsPlaceholder: string;
    loadingInstructionsMax: string;
    licenseNumberLabel: string;
    licenseNumberPlaceholder: string;
    licenseNumberRequired: string;
    licenseNumberInvalid: string;
    licenseStateLabel: string;
    licenseCountryLabel: string;
    licenseExpirationLabel: string;
    licenseExpirationRequired: string;
    licenseExpirationInvalid: string;
    licenseExpired: string;
    licenseExpirationTooFar: string;
    locationLabel: string;
    reviewButton: string;
    submitting: string;
    reviewNote: string;
    reviewTitle: string;
    editButton: string;
    submitButton: string;
}

interface Translations {
    checkInForm: CheckInFormTranslations;
    purchaseOrders: PurchaseOrderTranslations;
}

interface PageProps {
    location: Location;
    fields: FieldFlags;
    translations: Translations;
    truckColors: string[];
    auth: { user: { name: string; cellphone: string | null } | null };
    driverLicense: {
        number: string | null;
        state: string | null;
        country: string | null;
        expirationDate: string | null;
    } | null;
    [key: string]: unknown;
}

const formatPlate = (raw: string) =>
    raw
        .toUpperCase()
        .replace(/[^A-Z0-9 -]/g, '')
        .substring(0, 10);

const formatDigits = (raw: string, maxLength: number) =>
    raw.replace(/\D/g, '').substring(0, maxLength);

// ── Review summary ──────────────────────────────────────────────────────────

interface ReviewValues {
    customer: string;
    destination_city: string;
    destination_state: string;
    destination_country: string;
    loading_instructions?: string;
    po_numbers: { value: string }[];
    truck_name: string;
    truck_plate: string;
    truck_plate_state?: string;
    truck_plate_country?: string;
    truck_color?: string;
    trailer_plate: string;
    trailer_plate_state?: string;
    trailer_plate_country?: string;
    trailer_chute: string;
    empty_weight_lbs?: string;
    drivers_name: string;
    drivers_cellphone: string;
    drivers_license_number: string;
    drivers_license_state?: string;
    drivers_license_country?: string;
    drivers_license_expiration_date?: string;
}

function ReviewRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex justify-between gap-4 px-4 py-3">
            <dt className="shrink-0 text-xs font-medium text-gray-500 dark:text-gray-400">
                {label}
            </dt>
            <dd className="text-right text-sm text-gray-900 dark:text-gray-100">
                {value}
            </dd>
        </div>
    );
}

function ReviewSummary({
    location,
    fields,
    values,
    t,
    poTranslations,
}: {
    location: Location;
    fields: FieldFlags;
    values: ReviewValues;
    t: CheckInFormTranslations;
    poTranslations: PurchaseOrderTranslations;
}) {
    const formattedExpiration = values.drivers_license_expiration_date
        ? format(
              new Date(values.drivers_license_expiration_date + 'T00:00:00'),
              'MMMM d, yyyy',
          )
        : '';

    const trailerChuteLabels: Record<string, string> = {
        'n/a': t.trailerChuteNa,
        'center-chute': t.trailerChuteCenter,
        'side-chute': t.trailerChuteSide,
    };

    // Combines a state and country into one line, e.g. "Arizona, United States".
    const stateCountry = (
        state?: string | null,
        country?: string | null,
    ): string =>
        [state, country ? countryName(country) : null]
            .filter(Boolean)
            .join(', ');

    return (
        <div className="space-y-5 px-5 pt-5 pb-6">
            <SectionHeader step={1} label={t.reviewTitle} />

            <div className="border-gray-150/50 overflow-hidden rounded-lg border bg-gray-100/50 dark:border-gray-900/50 dark:bg-gray-950/30">
                <dl className="divide-y divide-gray-200 dark:divide-gray-700/60">
                    <div className="flex justify-between gap-4 px-4 py-3">
                        <dt className="text-xs font-medium text-gray-500 dark:text-gray-400">
                            {t.locationLabel}
                        </dt>
                        <dd className="text-right text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {location.name}
                        </dd>
                    </div>
                    <ReviewRow
                        label={t.customerLabel}
                        value={values.customer}
                    />
                    <ReviewRow
                        label={t.destinationLabel}
                        value={`${values.destination_city}, ${values.destination_state}${
                            values.destination_country
                                ? ` ${countryName(values.destination_country)}`
                                : ''
                        }`}
                    />
                    {!!values.loading_instructions && (
                        <ReviewRow
                            label={t.loadingInstructionsLabel}
                            value={values.loading_instructions}
                        />
                    )}
                    <ReviewRow
                        label={poTranslations.label}
                        value={values.po_numbers
                            .map((po) => po.value)
                            .join(', ')}
                    />
                    <ReviewRow
                        label={t.truckNameLabel}
                        value={values.truck_name}
                    />
                    {fields.showTruckColor && (
                        <ReviewRow
                            label={t.truckColorLabel}
                            value={
                                values.truck_color
                                    ? (t.truckColors[values.truck_color] ??
                                      values.truck_color)
                                    : ''
                            }
                        />
                    )}
                    <ReviewRow
                        label={t.truckPlateLabel}
                        value={values.truck_plate}
                    />
                    {(fields.showTruckPlateState ||
                        fields.showTruckPlateCountry) && (
                        <ReviewRow
                            label={t.truckPlateStateLabel}
                            value={stateCountry(
                                values.truck_plate_state,
                                values.truck_plate_country,
                            )}
                        />
                    )}
                    <ReviewRow
                        label={t.trailerPlateLabel}
                        value={values.trailer_plate}
                    />
                    {(fields.showTrailerPlateState ||
                        fields.showTrailerPlateCountry) && (
                        <ReviewRow
                            label={t.trailerPlateStateLabel}
                            value={stateCountry(
                                values.trailer_plate_state,
                                values.trailer_plate_country,
                            )}
                        />
                    )}
                    <ReviewRow
                        label={t.trailerChuteLabel}
                        value={
                            trailerChuteLabels[values.trailer_chute] ??
                            values.trailer_chute
                        }
                    />
                    {fields.showEmptyWeightLbs && (
                        <ReviewRow
                            label={t.emptyWeightLabel}
                            value={`${Number(values.empty_weight_lbs ?? 0).toLocaleString()} lbs`}
                        />
                    )}
                    <ReviewRow
                        label={t.driversNameLabel}
                        value={values.drivers_name}
                    />
                    <ReviewRow
                        label={t.cellphoneLabel}
                        value={`+1 ${values.drivers_cellphone}`}
                    />
                    <ReviewRow
                        label={t.licenseNumberLabel}
                        value={values.drivers_license_number}
                    />
                    {(fields.showDriversLicenseState ||
                        fields.showDriversLicenseCountry) && (
                        <ReviewRow
                            label={t.licenseStateLabel}
                            value={stateCountry(
                                values.drivers_license_state,
                                values.drivers_license_country,
                            )}
                        />
                    )}
                    {fields.showDriversLicenseExpirationDate && (
                        <ReviewRow
                            label={t.licenseExpirationLabel}
                            value={formattedExpiration}
                        />
                    )}
                </dl>
            </div>
        </div>
    );
}

// ── Page ─────────────────────────────────────────────────────────────────────

export default function CheckInForm({
    location,
    fields,
    truckColors,
}: PageProps) {
    const { translations, auth, driverLicense } = usePage<PageProps>().props;
    const t = translations.checkInForm;
    const poT = translations.purchaseOrders;

    // Prefill the driver's identity for signed-in drivers, from their saved
    // profile. Guests start with blank fields.
    const prefillName = auth.user?.name ?? '';
    const prefillCellphone = auth.user?.cellphone
        ? formatUsPhone(auth.user.cellphone.replace(/^\+1/, ''))
        : '';
    const prefillLicenseNumber = driverLicense?.number ?? '';
    const prefillLicenseState = driverLicense?.state ?? '';
    const prefillLicenseCountry = driverLicense?.country ?? '';
    const prefillLicenseExpiration = driverLicense?.expirationDate ?? '';

    const trailerChuteOptions = [
        { value: 'n/a', label: t.trailerChuteNa },
        { value: 'center-chute', label: t.trailerChuteCenter },
        { value: 'side-chute', label: t.trailerChuteSide },
    ];

    const truckColorOptions = truckColors.map((slug) => ({
        value: slug,
        label: t.truckColors[slug] ?? slug,
    }));

    const [processing, setProcessing] = useState(false);
    const [step, setStep] = useState<'form' | 'review'>('form');
    const [serverError, setServerError] = useState<string | null>(null);

    // License must still be valid beyond today; allow picking up to 60 years out
    // (the widest real-world US window is Arizona's "valid until age 65").
    const licenseMinDate = useMemo(() => {
        const tomorrow = new Date();
        tomorrow.setHours(0, 0, 0, 0);
        tomorrow.setDate(tomorrow.getDate() + 1);

        return tomorrow;
    }, []);

    const licenseMaxDate = useMemo(() => {
        const max = new Date();
        max.setHours(0, 0, 0, 0);
        max.setFullYear(max.getFullYear() + 60);

        return max;
    }, []);

    const schema = useMemo(() => {
        const optionalUnless = (
            shown: boolean,
            shownSchema: z.ZodString,
        ): z.ZodString | z.ZodOptional<z.ZodString> =>
            shown ? shownSchema : z.string().optional();

        return z.object({
            customer: z
                .string()
                .min(1, t.customerRequired)
                .min(2, t.customerMin)
                .max(40, t.customerMax),
            destination_city: z
                .string()
                .min(1, t.destinationCityRequired)
                .min(2, t.destinationCityMin)
                .max(30, t.destinationCityMin),
            destination_state: z
                .string()
                .min(1, t.destinationStateRequired)
                .min(2, t.destinationStateMin)
                .max(30, t.destinationStateMin),
            destination_country: z
                .string()
                .min(1, t.destinationCountryRequired),
            loading_instructions: z
                .string()
                .max(500, t.loadingInstructionsMax)
                .optional(),
            po_numbers: poNumberSchema(poT),
            truck_name: z
                .string()
                .min(1, t.truckNameRequired)
                .max(30, t.truckNameMax),
            truck_plate: z
                .string()
                .min(1, t.truckPlateRequired)
                .regex(/^[A-Z0-9 -]{2,10}$/, t.plateInvalid),
            truck_plate_state: optionalUnless(
                fields.showTruckPlateState,
                z
                    .string()
                    .min(1, t.stateRequired)
                    .min(2, t.stateMin)
                    .max(30, t.stateMax),
            ),
            truck_plate_country: optionalUnless(
                fields.showTruckPlateCountry,
                z
                    .string()
                    .min(1, t.countryCodeRequired)
                    .regex(/^[A-Z]{2}$/, t.countryCodeInvalid),
            ),
            truck_color: optionalUnless(
                fields.showTruckColor,
                z.string().min(1, t.truckColorRequired),
            ),
            trailer_plate: z
                .string()
                .min(1, t.trailerPlateRequired)
                .regex(/^[A-Z0-9 -]{2,10}$/, t.plateInvalid),
            trailer_plate_state: optionalUnless(
                fields.showTrailerPlateState,
                z
                    .string()
                    .min(1, t.stateRequired)
                    .min(2, t.stateMin)
                    .max(30, t.stateMax),
            ),
            trailer_plate_country: optionalUnless(
                fields.showTrailerPlateCountry,
                z
                    .string()
                    .min(1, t.countryCodeRequired)
                    .regex(/^[A-Z]{2}$/, t.countryCodeInvalid),
            ),
            trailer_chute: z.enum(['n/a', 'center-chute', 'side-chute']),
            empty_weight_lbs: optionalUnless(
                fields.showEmptyWeightLbs,
                z
                    .string()
                    .min(1, t.emptyWeightRequired)
                    .regex(/^\d{4,5}$/, t.emptyWeightInvalid),
            ),
            drivers_name: z
                .string()
                .min(1, t.driversNameRequired)
                .min(2, t.driversNameMin)
                .max(28, t.driversNameMax),
            drivers_cellphone: z
                .string()
                .regex(/^\(\d{3}\) \d{3}-\d{4}$/, t.cellphoneInvalid),
            drivers_license_number: z
                .string()
                .min(1, t.licenseNumberRequired)
                .min(4, t.licenseNumberInvalid)
                .max(20, t.licenseNumberInvalid),
            drivers_license_state: optionalUnless(
                fields.showDriversLicenseState,
                z
                    .string()
                    .min(1, t.stateRequired)
                    .min(2, t.stateMin)
                    .max(30, t.stateMax),
            ),
            drivers_license_country: optionalUnless(
                fields.showDriversLicenseCountry,
                z
                    .string()
                    .min(1, t.countryCodeRequired)
                    .regex(/^[A-Z]{2}$/, t.countryCodeInvalid),
            ),
            drivers_license_expiration_date: optionalUnless(
                fields.showDriversLicenseExpirationDate,
                z
                    .string()
                    .min(1, t.licenseExpirationRequired)
                    .refine(
                        (d) => new Date(d + 'T00:00:00') > new Date(),
                        t.licenseExpired,
                    )
                    .refine((d) => {
                        const max = new Date();
                        max.setFullYear(max.getFullYear() + 60);

                        return new Date(d + 'T00:00:00') <= max;
                    }, t.licenseExpirationTooFar) as unknown as z.ZodString,
            ),
        });
    }, [fields, t, poT]);

    type FormValues = z.infer<typeof schema>;

    const { control, handleSubmit, setError, getValues, setValue } =
        useForm<FormValues>({
            resolver: zodResolver(schema),
            defaultValues: {
                customer: '',
                destination_city: '',
                destination_state: '',
                destination_country: '',
                loading_instructions: '',
                po_numbers: [{ value: '' }],
                truck_name: '',
                truck_plate: '',
                truck_plate_state: '',
                truck_plate_country: '',
                truck_color: '',
                trailer_plate: '',
                trailer_plate_state: '',
                trailer_plate_country: '',
                trailer_chute: 'n/a',
                empty_weight_lbs: '',
                drivers_name: prefillName,
                drivers_cellphone: prefillCellphone,
                drivers_license_number: prefillLicenseNumber,
                drivers_license_state: prefillLicenseState,
                drivers_license_country: prefillLicenseCountry,
                drivers_license_expiration_date: prefillLicenseExpiration,
            },
        });

    const {
        fields: poFields,
        append,
        remove,
    } = useFieldArray({
        control,
        name: 'po_numbers',
    });

    const onReview = () => {
        handleSubmit(() => setStep('review'))();
    };

    const formFieldKeys: Array<keyof FormValues> = [
        'customer',
        'destination_city',
        'destination_state',
        'destination_country',
        'loading_instructions',
        'truck_name',
        'truck_plate',
        'truck_plate_state',
        'truck_plate_country',
        'truck_color',
        'trailer_plate',
        'trailer_plate_state',
        'trailer_plate_country',
        'trailer_chute',
        'empty_weight_lbs',
        'drivers_name',
        'drivers_cellphone',
        'drivers_license_number',
        'drivers_license_state',
        'drivers_license_country',
        'drivers_license_expiration_date',
    ];

    const onSubmit = () => {
        const values = getValues();
        setProcessing(true);
        setServerError(null);

        const payload = {
            customer: values.customer,
            destination_city: values.destination_city,
            destination_state: values.destination_state,
            destination_country: values.destination_country,
            loading_instructions: values.loading_instructions ?? '',
            po_numbers: values.po_numbers.map((po) => po.value),
            truck_name: values.truck_name,
            truck_plate: values.truck_plate,
            trailer_plate: values.trailer_plate,
            trailer_chute: values.trailer_chute,
            drivers_name: values.drivers_name,
            drivers_cellphone: values.drivers_cellphone,
            drivers_license_number: values.drivers_license_number,
            ...(fields.showTruckColor && {
                truck_color: values.truck_color ?? '',
            }),
            ...(fields.showTruckPlateState && {
                truck_plate_state: values.truck_plate_state ?? '',
            }),
            ...(fields.showTruckPlateCountry && {
                truck_plate_country: values.truck_plate_country ?? '',
            }),
            ...(fields.showTrailerPlateState && {
                trailer_plate_state: values.trailer_plate_state ?? '',
            }),
            ...(fields.showTrailerPlateCountry && {
                trailer_plate_country: values.trailer_plate_country ?? '',
            }),
            ...(fields.showEmptyWeightLbs && {
                empty_weight_lbs: values.empty_weight_lbs ?? '',
            }),
            ...(fields.showDriversLicenseState && {
                drivers_license_state: values.drivers_license_state ?? '',
            }),
            ...(fields.showDriversLicenseCountry && {
                drivers_license_country: values.drivers_license_country ?? '',
            }),
            ...(fields.showDriversLicenseExpirationDate && {
                drivers_license_expiration_date:
                    values.drivers_license_expiration_date ?? '',
            }),
        };

        router.post(store.url({ uuid: location.id }), payload, {
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

    const renderTextField = (
        name: keyof FormValues,
        label: string,
        placeholder: string,
        options?: {
            maxLength?: number;
            format?: (raw: string) => string;
            inputMode?: 'numeric';
        },
    ) => (
        <Controller
            name={name}
            control={control}
            render={({ field, fieldState }) => (
                <Field data-invalid={fieldState.invalid}>
                    <FieldLabel htmlFor={name}>{label}</FieldLabel>
                    <Input
                        {...field}
                        value={(field.value as string) ?? ''}
                        onChange={(e) =>
                            field.onChange(
                                options?.format
                                    ? options.format(e.target.value)
                                    : e.target.value,
                            )
                        }
                        id={name}
                        type="text"
                        inputMode={options?.inputMode}
                        maxLength={options?.maxLength}
                        aria-invalid={fieldState.invalid}
                        placeholder={placeholder}
                    />
                    {fieldState.invalid && (
                        <FieldError errors={[fieldState.error]} />
                    )}
                </Field>
            )}
        />
    );

    const renderSelectField = (
        name: keyof FormValues,
        label: string,
        selectOptions: { value: string; label: string }[],
        placeholder?: string,
    ) => (
        <Controller
            name={name}
            control={control}
            render={({ field, fieldState }) => (
                <Field data-invalid={fieldState.invalid}>
                    <FieldLabel htmlFor={name}>{label}</FieldLabel>
                    <div className="relative">
                        <select
                            {...field}
                            value={(field.value as string) ?? ''}
                            id={name}
                            aria-invalid={fieldState.invalid}
                            className="h-9 w-full appearance-none rounded-4xl border border-input bg-input/30 px-3.5 pr-9 text-base transition-colors outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-[3px] aria-invalid:ring-destructive/20 md:text-sm dark:aria-invalid:border-destructive/50 dark:aria-invalid:ring-destructive/40"
                        >
                            {placeholder && (
                                <option value="" disabled>
                                    {placeholder}
                                </option>
                            )}
                            {selectOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <ChevronDownIcon className="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    </div>
                    {fieldState.invalid && (
                        <FieldError errors={[fieldState.error]} />
                    )}
                </Field>
            )}
        />
    );

    // Read-only country: populated only by the location autocomplete.
    const renderCountryDisplay = (
        name: keyof FormValues,
        label: string,
        placeholder: string,
    ) => (
        <Controller
            name={name}
            control={control}
            render={({ field, fieldState }) => (
                <Field data-invalid={fieldState.invalid}>
                    <FieldLabel htmlFor={name}>{label}</FieldLabel>
                    <CountryDisplay
                        id={name}
                        value={(field.value as string) ?? ''}
                        invalid={fieldState.invalid}
                        placeholder={placeholder}
                    />
                    {fieldState.invalid && (
                        <FieldError errors={[fieldState.error]} />
                    )}
                </Field>
            )}
        />
    );

    // Read-only text field (e.g. destination state) filled only by the city pick.
    const renderReadOnlyField = (
        name: keyof FormValues,
        label: string,
        placeholder: string,
    ) => (
        <Controller
            name={name}
            control={control}
            render={({ field, fieldState }) => (
                <Field data-invalid={fieldState.invalid}>
                    <FieldLabel htmlFor={name}>{label}</FieldLabel>
                    <div
                        id={name}
                        aria-invalid={fieldState.invalid || undefined}
                        className="flex h-9 w-full items-center rounded-4xl border border-input bg-muted/40 px-3.5 text-sm shadow-xs aria-invalid:border-destructive"
                    >
                        {field.value ? (
                            <span className="text-foreground">
                                {field.value as string}
                            </span>
                        ) : (
                            <span className="text-muted-foreground">
                                {placeholder}
                            </span>
                        )}
                    </div>
                    {fieldState.invalid && (
                        <FieldError errors={[fieldState.error]} />
                    )}
                </Field>
            )}
        />
    );

    const renderCityAutocomplete = (
        name: keyof FormValues,
        stateField: keyof FormValues,
        countryField: keyof FormValues,
        label: string,
        placeholder: string,
    ) => (
        <Controller
            name={name}
            control={control}
            render={({ field, fieldState }) => (
                <Field data-invalid={fieldState.invalid}>
                    <FieldLabel htmlFor={name}>{label}</FieldLabel>
                    <LocationAutocomplete
                        level="city"
                        id={name}
                        value={(field.value as string) ?? ''}
                        onChange={(value) => {
                            field.onChange(value);
                            // Typing invalidates the derived fields — they only
                            // ever come from a dropdown pick.
                            setValue(stateField, '', { shouldDirty: true });
                            setValue(countryField, '', { shouldDirty: true });
                        }}
                        onBlur={field.onBlur}
                        placeholder={placeholder}
                        maxLength={30}
                        invalid={fieldState.invalid}
                        onSelect={(result) => {
                            field.onChange(result.city ?? '');
                            setValue(stateField, result.state, {
                                shouldValidate: true,
                                shouldDirty: true,
                            });
                            setValue(countryField, result.countryCode, {
                                shouldValidate: true,
                                shouldDirty: true,
                            });
                        }}
                    />
                    {fieldState.invalid && (
                        <FieldError errors={[fieldState.error]} />
                    )}
                </Field>
            )}
        />
    );

    const renderStateAutocomplete = (
        name: keyof FormValues,
        countryField: keyof FormValues,
        label: string,
        placeholder: string,
    ) => (
        <Controller
            name={name}
            control={control}
            render={({ field, fieldState }) => (
                <Field data-invalid={fieldState.invalid}>
                    <FieldLabel htmlFor={name}>{label}</FieldLabel>
                    <LocationAutocomplete
                        level="state"
                        id={name}
                        value={(field.value as string) ?? ''}
                        onChange={(value) => {
                            field.onChange(value);
                            // Typing invalidates the derived country.
                            setValue(countryField, '', { shouldDirty: true });
                        }}
                        onBlur={field.onBlur}
                        placeholder={placeholder}
                        maxLength={30}
                        invalid={fieldState.invalid}
                        onSelect={(result) => {
                            field.onChange(result.state);
                            setValue(countryField, result.countryCode, {
                                shouldValidate: true,
                                shouldDirty: true,
                            });
                        }}
                    />
                    {fieldState.invalid && (
                        <FieldError errors={[fieldState.error]} />
                    )}
                </Field>
            )}
        />
    );

    return (
        <PublicLayout>
            <Head title={t.pageTitle} />

            <div className="px-4 py-6 sm:py-10">
                <div className="mx-auto max-w-md lg:max-w-xl">
                    {/* Page title */}
                    <div className="mb-6">
                        <p className="mb-1 text-[10px] font-bold tracking-widest text-brand-green uppercase">
                            {t.newCheckIn}
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
                                {/* ── Section 1: Check-In Details ──────────── */}
                                <div className="px-5 pt-5 pb-6">
                                    <SectionHeader
                                        step={1}
                                        label={t.checkInDetails}
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

                                    <FieldGroup className="mt-5">
                                        {renderTextField(
                                            'customer',
                                            t.customerLabel,
                                            t.customerPlaceholder,
                                            { maxLength: 40 },
                                        )}

                                        {renderCityAutocomplete(
                                            'destination_city',
                                            'destination_state',
                                            'destination_country',
                                            t.destinationCityLabel,
                                            t.destinationCityPlaceholder,
                                        )}

                                        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                            {renderReadOnlyField(
                                                'destination_state',
                                                t.destinationStateLabel,
                                                t.destinationStatePlaceholder,
                                            )}
                                            {renderCountryDisplay(
                                                'destination_country',
                                                t.destinationCountryLabel,
                                                t.destinationCountryPlaceholder,
                                            )}
                                        </div>

                                        <Controller
                                            name="loading_instructions"
                                            control={control}
                                            render={({ field, fieldState }) => (
                                                <Field
                                                    data-invalid={
                                                        fieldState.invalid
                                                    }
                                                >
                                                    <FieldLabel htmlFor="loading_instructions">
                                                        {
                                                            t.loadingInstructionsLabel
                                                        }
                                                    </FieldLabel>
                                                    <Textarea
                                                        {...field}
                                                        value={
                                                            (field.value as string) ??
                                                            ''
                                                        }
                                                        id="loading_instructions"
                                                        maxLength={500}
                                                        aria-invalid={
                                                            fieldState.invalid
                                                        }
                                                        placeholder={
                                                            t.loadingInstructionsPlaceholder
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

                                        <PurchaseOrderInputs
                                            control={control}
                                            fields={poFields}
                                            append={append}
                                            remove={remove}
                                            translations={poT}
                                        />
                                    </FieldGroup>
                                </div>

                                {/* Divider */}
                                <div className="mx-5 border-t border-dashed border-gray-200 dark:border-gray-700" />

                                {/* ── Section 2: Truck & Trailer ───────────── */}
                                <div className="px-5 pt-5 pb-6">
                                    <SectionHeader
                                        step={2}
                                        label={t.truckTrailer}
                                    />

                                    <FieldGroup className="mt-4">
                                        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 sm:[&>:last-child:nth-child(odd)]:col-span-2">
                                            {renderTextField(
                                                'truck_name',
                                                t.truckNameLabel,
                                                t.truckNamePlaceholder,
                                                { maxLength: 30 },
                                            )}
                                            {fields.showTruckColor &&
                                                renderSelectField(
                                                    'truck_color',
                                                    t.truckColorLabel,
                                                    truckColorOptions,
                                                    t.truckColorPlaceholder,
                                                )}
                                        </div>

                                        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 sm:[&>:last-child:nth-child(odd)]:col-span-2">
                                            {renderTextField(
                                                'truck_plate',
                                                t.truckPlateLabel,
                                                t.platePlaceholder,
                                                {
                                                    maxLength: 10,
                                                    format: formatPlate,
                                                },
                                            )}
                                            {fields.showTruckPlateState &&
                                                renderStateAutocomplete(
                                                    'truck_plate_state',
                                                    'truck_plate_country',
                                                    t.truckPlateStateLabel,
                                                    t.statePlaceholder,
                                                )}
                                            {fields.showTruckPlateCountry &&
                                                renderCountryDisplay(
                                                    'truck_plate_country',
                                                    t.truckPlateCountryLabel,
                                                    t.destinationCountryPlaceholder,
                                                )}
                                        </div>

                                        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 sm:[&>:last-child:nth-child(odd)]:col-span-2">
                                            {renderTextField(
                                                'trailer_plate',
                                                t.trailerPlateLabel,
                                                t.platePlaceholder,
                                                {
                                                    maxLength: 10,
                                                    format: formatPlate,
                                                },
                                            )}
                                            {fields.showTrailerPlateState &&
                                                renderStateAutocomplete(
                                                    'trailer_plate_state',
                                                    'trailer_plate_country',
                                                    t.trailerPlateStateLabel,
                                                    t.statePlaceholder,
                                                )}
                                            {fields.showTrailerPlateCountry &&
                                                renderCountryDisplay(
                                                    'trailer_plate_country',
                                                    t.trailerPlateCountryLabel,
                                                    t.destinationCountryPlaceholder,
                                                )}
                                        </div>

                                        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 sm:[&>:last-child:nth-child(odd)]:col-span-2">
                                            {renderSelectField(
                                                'trailer_chute',
                                                t.trailerChuteLabel,
                                                trailerChuteOptions,
                                            )}
                                            {fields.showEmptyWeightLbs &&
                                                renderTextField(
                                                    'empty_weight_lbs',
                                                    t.emptyWeightLabel,
                                                    t.emptyWeightPlaceholder,
                                                    {
                                                        maxLength: 5,
                                                        inputMode: 'numeric',
                                                        format: (raw) =>
                                                            formatDigits(
                                                                raw,
                                                                5,
                                                            ),
                                                    },
                                                )}
                                        </div>
                                    </FieldGroup>
                                </div>

                                {/* Divider */}
                                <div className="mx-5 border-t border-dashed border-gray-200 dark:border-gray-700" />

                                {/* ── Section 3: Driver Information ────────── */}
                                <div className="px-5 pt-5 pb-5">
                                    <SectionHeader
                                        step={3}
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

                                        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 sm:[&>:last-child:nth-child(odd)]:col-span-2">
                                            {renderTextField(
                                                'drivers_license_number',
                                                t.licenseNumberLabel,
                                                t.licenseNumberPlaceholder,
                                                { maxLength: 20 },
                                            )}
                                            {fields.showDriversLicenseState &&
                                                renderStateAutocomplete(
                                                    'drivers_license_state',
                                                    'drivers_license_country',
                                                    t.licenseStateLabel,
                                                    t.statePlaceholder,
                                                )}
                                            {fields.showDriversLicenseCountry &&
                                                renderCountryDisplay(
                                                    'drivers_license_country',
                                                    t.licenseCountryLabel,
                                                    t.destinationCountryPlaceholder,
                                                )}
                                        </div>

                                        {fields.showDriversLicenseExpirationDate && (
                                            <Controller
                                                name="drivers_license_expiration_date"
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
                                                            {
                                                                t.licenseExpirationLabel
                                                            }
                                                        </FieldLabel>
                                                        <DatePicker
                                                            date={
                                                                field.value
                                                                    ? new Date(
                                                                          field.value +
                                                                              'T00:00:00',
                                                                      )
                                                                    : undefined
                                                            }
                                                            onDateChange={(d) =>
                                                                field.onChange(
                                                                    d
                                                                        ? format(
                                                                              d,
                                                                              'yyyy-MM-dd',
                                                                          )
                                                                        : '',
                                                                )
                                                            }
                                                            minDate={
                                                                licenseMinDate
                                                            }
                                                            maxDate={
                                                                licenseMaxDate
                                                            }
                                                            endMonth={
                                                                licenseMaxDate
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
                                        )}

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
                                    fields={fields}
                                    values={getValues() as ReviewValues}
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
