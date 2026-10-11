import { useForm } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';

import CheckInUpdateController from '@/actions/App/Http/Controllers/Dashboard/CheckInUpdateController';
import type {
    CheckInFormFields,
    CheckInRecord,
} from '@/components/dashboard/types';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { formatLabel } from '@/lib/dashboard/format';

const MAX_PURCHASE_ORDERS = 10;

/** The TrailerChute enum's values, as the chute select offers them. */
const TRAILER_CHUTES: Record<string, string> = {
    'n/a': 'Not applicable',
    'center-chute': 'Center chute',
    'side-chute': 'Side chute',
};

const SECONDARY_BUTTON =
    'btn gap-2 border-gray-200 bg-white text-gray-600 hover:border-gray-300 dark:border-gray-700/60 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-gray-600';

/**
 * What the edit submits. Optional keys are only present when the location's form shows that
 * field: the endpoint rejects any field it does not expect, so hidden ones must not be sent.
 */
type EditCheckInData = {
    drivers_name: string;
    drivers_cellphone: string;
    drivers_email: string;
    drivers_license_number: string;
    drivers_license_state?: string;
    drivers_license_country?: string;
    drivers_license_expiration_date?: string;
    customer: string;
    destination_city: string;
    destination_state: string;
    destination_country: string;
    po_numbers: string[];
    loading_instructions: string;
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
};

type TextKey = {
    [K in keyof EditCheckInData]-?: EditCheckInData[K] extends
        string | undefined
        ? K
        : never;
}[keyof EditCheckInData];

function initialData(
    checkIn: CheckInRecord,
    fields: CheckInFormFields,
): EditCheckInData {
    const { driver, truck, trailer, destination } = checkIn;

    return {
        drivers_name: driver.name,
        drivers_cellphone: driver.cellphone,
        drivers_email: driver.email ?? '',
        drivers_license_number: '',
        ...(fields.showDriversLicenseState && {
            drivers_license_state: driver.licenseState,
        }),
        ...(fields.showDriversLicenseCountry && {
            drivers_license_country: driver.licenseCountry,
        }),
        ...(fields.showDriversLicenseExpirationDate && {
            drivers_license_expiration_date: driver.licenseExpirationDate ?? '',
        }),
        customer: checkIn.customer,
        destination_city: destination.city,
        destination_state: destination.state,
        destination_country: destination.country,
        po_numbers:
            checkIn.purchaseOrders.length > 0 ? checkIn.purchaseOrders : [''],
        loading_instructions: checkIn.loadingInstructions ?? '',
        truck_name: truck.name,
        truck_plate: truck.plate,
        ...(fields.showTruckPlateState && {
            truck_plate_state: truck.plateState,
        }),
        ...(fields.showTruckPlateCountry && {
            truck_plate_country: truck.plateCountry,
        }),
        ...(fields.showTruckColor && { truck_color: truck.color }),
        trailer_plate: trailer.plate,
        ...(fields.showTrailerPlateState && {
            trailer_plate_state: trailer.plateState,
        }),
        ...(fields.showTrailerPlateCountry && {
            trailer_plate_country: trailer.plateCountry,
        }),
        trailer_chute: trailer.chute,
        ...(fields.showEmptyWeightLbs && {
            empty_weight_lbs:
                checkIn.emptyWeightLbs === null
                    ? ''
                    : String(checkIn.emptyWeightLbs),
        }),
    };
}

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section>
            <h3 className="mb-3 text-xs font-semibold text-gray-400 uppercase dark:text-gray-500">
                {title}
            </h3>
            <div className="grid gap-4 sm:grid-cols-2">{children}</div>
        </section>
    );
}

/**
 * Edits a check-in's driver, load and equipment details in one submit. Every changed field lands
 * on the history timeline with its before and after value, which the redirect back refreshes.
 */
export default function EditCheckInDialog({
    open,
    onOpenChange,
    checkIn,
    fields,
    truckColors,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    checkIn: CheckInRecord;
    fields: CheckInFormFields;
    truckColors: string[];
}) {
    const form = useForm<EditCheckInData>(initialData(checkIn, fields));
    const errors = form.errors as Record<string, string | undefined>;

    const changeOpen = (next: boolean) => {
        if (next) {
            // Start from the record as it is now, which may have changed since the last edit.
            form.setDefaults(initialData(checkIn, fields));
            form.reset();
        }

        form.clearErrors();
        onOpenChange(next);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (form.processing) {
            return;
        }

        form.submit(CheckInUpdateController(checkIn.uuid), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    const text = (key: TextKey, label: string, props: object = {}) => (
        <Field data-invalid={!!errors[key] || undefined}>
            <FieldLabel htmlFor={`edit_${key}`}>{label}</FieldLabel>
            <Input
                id={`edit_${key}`}
                value={form.data[key] ?? ''}
                aria-invalid={!!errors[key] || undefined}
                onChange={(event) => form.setData(key, event.target.value)}
                {...props}
            />
            <FieldError>{errors[key]}</FieldError>
        </Field>
    );

    const setPurchaseOrder = (index: number, value: string) =>
        form.setData(
            'po_numbers',
            form.data.po_numbers.map((number, i) =>
                i === index ? value : number,
            ),
        );

    // Errors on fields the form does not render, such as a refused unknown field.
    const renderedKeys = new Set(Object.keys(form.data));
    const otherErrors = Object.entries(errors).filter(
        ([key, message]) =>
            message !== undefined &&
            !renderedKeys.has(key) &&
            !key.startsWith('po_numbers.'),
    );

    const licenseLastFour = checkIn.driver.licenseLastFour;

    return (
        <Dialog open={open} onOpenChange={changeOpen}>
            <DialogContent className="dashboard sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        Edit check-in {checkIn.referenceNumber}
                    </DialogTitle>
                    <DialogDescription>
                        Each changed field is recorded in the check-in's history
                        with its previous value.
                    </DialogDescription>
                </DialogHeader>

                <form
                    id="edit-check-in"
                    onSubmit={submit}
                    className="-mx-6 min-h-0 flex-1 space-y-6 overflow-y-auto px-6"
                >
                    {otherErrors.length > 0 && (
                        <div
                            role="alert"
                            className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600 dark:bg-red-500/10 dark:text-red-400"
                        >
                            {otherErrors.map(([key, message]) => (
                                <p key={key}>{message}</p>
                            ))}
                        </div>
                    )}

                    <Section title="Driver">
                        {text('drivers_name', 'Name', { maxLength: 28 })}
                        {text('drivers_cellphone', 'Cellphone', {
                            type: 'tel',
                            inputMode: 'tel',
                        })}
                        {text('drivers_email', 'Email', { type: 'email' })}
                        {text('drivers_license_number', 'License number', {
                            maxLength: 20,
                            autoComplete: 'off',
                            placeholder:
                                licenseLastFour !== ''
                                    ? `Leave blank to keep ••••${licenseLastFour}`
                                    : 'Leave blank to keep',
                        })}
                        {fields.showDriversLicenseState &&
                            text('drivers_license_state', 'License state', {
                                maxLength: 30,
                            })}
                        {fields.showDriversLicenseCountry &&
                            text('drivers_license_country', 'License country', {
                                maxLength: 2,
                            })}
                        {fields.showDriversLicenseExpirationDate &&
                            text(
                                'drivers_license_expiration_date',
                                'License expiration',
                                { type: 'date' },
                            )}
                    </Section>

                    <Section title="Load">
                        {text('customer', 'Customer', { maxLength: 40 })}
                        {text('destination_city', 'Destination city', {
                            maxLength: 30,
                        })}
                        {text('destination_state', 'Destination state', {
                            maxLength: 30,
                        })}
                        {text('destination_country', 'Destination country', {
                            maxLength: 2,
                        })}

                        <Field
                            className="sm:col-span-2"
                            data-invalid={!!errors.po_numbers || undefined}
                        >
                            <FieldLabel>Purchase orders</FieldLabel>
                            <div className="space-y-2">
                                {form.data.po_numbers.map((number, index) => {
                                    const error = errors[`po_numbers.${index}`];

                                    return (
                                        <div key={index}>
                                            <div className="flex items-center gap-2">
                                                <Input
                                                    aria-label={`Purchase order ${index + 1}`}
                                                    value={number}
                                                    maxLength={13}
                                                    placeholder="PO-1234"
                                                    className="font-mono"
                                                    aria-invalid={
                                                        !!error || undefined
                                                    }
                                                    onChange={(event) =>
                                                        setPurchaseOrder(
                                                            index,
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                                <button
                                                    type="button"
                                                    aria-label={`Remove purchase order ${index + 1}`}
                                                    disabled={
                                                        form.data.po_numbers
                                                            .length === 1
                                                    }
                                                    onClick={() =>
                                                        form.setData(
                                                            'po_numbers',
                                                            form.data.po_numbers.filter(
                                                                (_, i) =>
                                                                    i !== index,
                                                            ),
                                                        )
                                                    }
                                                    className="rounded-full p-1.5 text-gray-400 hover:text-rose-500 disabled:opacity-40 disabled:hover:text-gray-400"
                                                >
                                                    <X className="size-4" />
                                                </button>
                                            </div>
                                            <FieldError>{error}</FieldError>
                                        </div>
                                    );
                                })}
                            </div>
                            {form.data.po_numbers.length <
                                MAX_PURCHASE_ORDERS && (
                                <button
                                    type="button"
                                    onClick={() =>
                                        form.setData('po_numbers', [
                                            ...form.data.po_numbers,
                                            '',
                                        ])
                                    }
                                    className="btn-sm mt-1 gap-1.5 self-start border-gray-200 bg-white text-gray-600 hover:border-gray-300 dark:border-gray-700/60 dark:bg-gray-800 dark:text-gray-300"
                                >
                                    <Plus className="size-3.5" />
                                    Add purchase order
                                </button>
                            )}
                            <FieldError>{errors.po_numbers}</FieldError>
                        </Field>

                        <Field
                            className="sm:col-span-2"
                            data-invalid={
                                !!errors.loading_instructions || undefined
                            }
                        >
                            <FieldLabel htmlFor="edit_loading_instructions">
                                Loading instructions
                            </FieldLabel>
                            <Textarea
                                id="edit_loading_instructions"
                                rows={3}
                                maxLength={500}
                                value={form.data.loading_instructions}
                                aria-invalid={
                                    !!errors.loading_instructions || undefined
                                }
                                onChange={(event) =>
                                    form.setData(
                                        'loading_instructions',
                                        event.target.value,
                                    )
                                }
                            />
                            <FieldError>
                                {errors.loading_instructions}
                            </FieldError>
                        </Field>
                    </Section>

                    <Section title="Truck">
                        {text('truck_name', 'Name', { maxLength: 30 })}
                        {text('truck_plate', 'Plate', { maxLength: 10 })}
                        {fields.showTruckPlateState &&
                            text('truck_plate_state', 'Plate state', {
                                maxLength: 30,
                            })}
                        {fields.showTruckPlateCountry &&
                            text('truck_plate_country', 'Plate country', {
                                maxLength: 2,
                            })}
                        {fields.showTruckColor && (
                            <Field
                                data-invalid={!!errors.truck_color || undefined}
                            >
                                <FieldLabel htmlFor="edit_truck_color">
                                    Color
                                </FieldLabel>
                                <select
                                    id="edit_truck_color"
                                    value={form.data.truck_color ?? ''}
                                    onChange={(event) =>
                                        form.setData(
                                            'truck_color',
                                            event.target.value,
                                        )
                                    }
                                    className="form-select w-full bg-white dark:bg-gray-800"
                                >
                                    <option value="" disabled>
                                        Select a color
                                    </option>
                                    {truckColors.map((color) => (
                                        <option key={color} value={color}>
                                            {formatLabel(color)}
                                        </option>
                                    ))}
                                </select>
                                <FieldError>{errors.truck_color}</FieldError>
                            </Field>
                        )}
                        {fields.showEmptyWeightLbs &&
                            text('empty_weight_lbs', 'Empty weight (lbs)', {
                                type: 'number',
                                inputMode: 'numeric',
                                min: 1000,
                                max: 99999,
                            })}
                    </Section>

                    <Section title="Trailer">
                        {text('trailer_plate', 'Plate', { maxLength: 10 })}
                        {fields.showTrailerPlateState &&
                            text('trailer_plate_state', 'Plate state', {
                                maxLength: 30,
                            })}
                        {fields.showTrailerPlateCountry &&
                            text('trailer_plate_country', 'Plate country', {
                                maxLength: 2,
                            })}
                        <Field
                            data-invalid={!!errors.trailer_chute || undefined}
                        >
                            <FieldLabel htmlFor="edit_trailer_chute">
                                Chute
                            </FieldLabel>
                            <select
                                id="edit_trailer_chute"
                                value={form.data.trailer_chute}
                                onChange={(event) =>
                                    form.setData(
                                        'trailer_chute',
                                        event.target.value,
                                    )
                                }
                                className="form-select w-full bg-white dark:bg-gray-800"
                            >
                                {Object.entries(TRAILER_CHUTES).map(
                                    ([chute, label]) => (
                                        <option key={chute} value={chute}>
                                            {label}
                                        </option>
                                    ),
                                )}
                            </select>
                            <FieldError>{errors.trailer_chute}</FieldError>
                        </Field>
                    </Section>
                </form>

                <DialogFooter>
                    <DialogClose asChild>
                        <button
                            type="button"
                            disabled={form.processing}
                            className={SECONDARY_BUTTON}
                        >
                            Cancel
                        </button>
                    </DialogClose>
                    <button
                        type="submit"
                        form="edit-check-in"
                        disabled={form.processing || !form.isDirty}
                        className="btn bg-gray-900 text-gray-100 hover:bg-gray-800 disabled:opacity-50 dark:bg-gray-100 dark:text-gray-800 dark:hover:bg-white"
                    >
                        Save changes
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
