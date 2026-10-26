import { Head, Link } from '@inertiajs/react';
import type { InertiaFormProps } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useState } from 'react';

import { update } from '@/actions/App/Http/Controllers/Dashboard/LocationCheckInOptionsController';
import EditableSection from '@/components/dashboard/location/EditableSection';
import EmailAddressInputs from '@/components/dashboard/location/EmailAddressInputs';
import {
    NotificationEmails,
    OptionRow,
    OptionRows,
    ShownBadge,
} from '@/components/dashboard/location/OptionRows';
import SectionHeader, {
    SectionGroup,
} from '@/components/dashboard/location/SectionHeader';
import type {
    CheckInFormFields,
    DashboardLocationSummary,
    LocationCheckInOptions,
} from '@/components/dashboard/types';
import { Switch } from '@/components/ui/switch';
import { useCan } from '@/hooks/useCan';
import DashboardLayout from '@/layouts/DashboardLayout';
import LocationLayout from '@/layouts/LocationLayout';
import dashboardRoutes from '@/routes/dashboard';

interface LocationCheckInOptionsProps {
    location: DashboardLocationSummary;
    options: LocationCheckInOptions;
}

type Section = 'fields' | 'emails';

/** The config flags App\Enums\CheckInFormField names, as the update endpoint takes them. */
type FormFieldName =
    | 'show_truck_color'
    | 'show_empty_weight_lbs'
    | 'show_truck_plate_state'
    | 'show_truck_plate_country'
    | 'show_trailer_plate_state'
    | 'show_trailer_plate_country'
    | 'show_drivers_license_state'
    | 'show_drivers_license_country'
    | 'show_drivers_license_expiration_date';

type FormFieldsData = Record<FormFieldName, boolean>;

const FORM_FIELDS: {
    key: keyof CheckInFormFields;
    name: FormFieldName;
    label: string;
}[] = [
    { key: 'showTruckColor', name: 'show_truck_color', label: 'Truck color' },
    {
        key: 'showEmptyWeightLbs',
        name: 'show_empty_weight_lbs',
        label: 'Empty weight (lbs)',
    },
    {
        key: 'showTruckPlateState',
        name: 'show_truck_plate_state',
        label: 'Truck plate state',
    },
    {
        key: 'showTruckPlateCountry',
        name: 'show_truck_plate_country',
        label: 'Truck plate country',
    },
    {
        key: 'showTrailerPlateState',
        name: 'show_trailer_plate_state',
        label: 'Trailer plate state',
    },
    {
        key: 'showTrailerPlateCountry',
        name: 'show_trailer_plate_country',
        label: 'Trailer plate country',
    },
    {
        key: 'showDriversLicenseState',
        name: 'show_drivers_license_state',
        label: "Driver's license state",
    },
    {
        key: 'showDriversLicenseCountry',
        name: 'show_drivers_license_country',
        label: "Driver's license country",
    },
    {
        key: 'showDriversLicenseExpirationDate',
        name: 'show_drivers_license_expiration_date',
        label: "Driver's license expiration date",
    },
];

/** One switch per optional field; a shown field is required on the form. */
function FormFieldSwitches({
    form,
}: {
    form: InertiaFormProps<FormFieldsData>;
}) {
    return (
        <>
            <OptionRows>
                {FORM_FIELDS.map((field) => (
                    <label
                        key={field.name}
                        htmlFor={`checkin_${field.name}`}
                        className="flex cursor-pointer items-center justify-between gap-4 py-2.5"
                    >
                        <span className="text-sm font-medium text-gray-800 dark:text-gray-100">
                            {field.label}
                        </span>
                        <Switch
                            id={`checkin_${field.name}`}
                            checked={form.data[field.name]}
                            onCheckedChange={(checked) =>
                                form.setData(field.name, checked)
                            }
                        />
                    </label>
                ))}
            </OptionRows>
            <p className="text-xs text-gray-500 dark:text-gray-400">
                Shown fields are required. Changes apply to new check-ins; past
                check-ins keep what they collected.
            </p>
        </>
    );
}

export default function LocationCheckInOptionsPage({
    location,
    options,
}: LocationCheckInOptionsProps) {
    const can = useCan();
    const [editing, setEditing] = useState<Section | null>(null);

    const sectionProps = (section: Section) => ({
        action: update(location.uuid),
        canEdit: can('edit check-in options'),
        editing: editing === section,
        locked: editing !== null && editing !== section,
        onEditingChange: (next: boolean) => setEditing(next ? section : null),
    });

    const formFields = Object.fromEntries(
        FORM_FIELDS.map((field) => [field.name, options.formFields[field.key]]),
    ) as FormFieldsData;

    return (
        <>
            <Head title={`${location.name} check-in options`} />

            <SectionHeader
                title="Check-in form & notifications"
                description="What drivers fill in when they check in here, and who hears about it."
            />

            <SectionGroup
                title="Arrival"
                action={
                    can('edit locations') ? (
                        <Link
                            href={dashboardRoutes.locations.details.url(
                                location.uuid,
                            )}
                            className="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-xs font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700/60 dark:hover:text-gray-200"
                        >
                            <Pencil className="size-3" />
                            Edit in Details
                        </Link>
                    ) : undefined
                }
            >
                <OptionRows>
                    <OptionRow
                        label="Check-in radius"
                        description="How far from the dock a driver may check in."
                    >
                        {options.maxDistanceAllowed} mi
                    </OptionRow>
                </OptionRows>
            </SectionGroup>

            <EditableSection
                title="Optional form fields"
                {...sectionProps('fields')}
                initialData={formFields}
                view={
                    <OptionRows>
                        {FORM_FIELDS.map((field) => (
                            <OptionRow key={field.key} label={field.label}>
                                <ShownBadge
                                    shown={options.formFields[field.key]}
                                />
                            </OptionRow>
                        ))}
                    </OptionRows>
                }
                form={(form) => <FormFieldSwitches form={form} />}
            />

            <EditableSection
                title="Notify on check-in"
                {...sectionProps('emails')}
                initialData={{ email_addresses: options.notificationEmails }}
                view={
                    <NotificationEmails
                        emails={options.notificationEmails}
                        empty="Nobody is emailed when a driver checks in here."
                    />
                }
                form={(form) => (
                    <EmailAddressInputs
                        form={form}
                        empty="Nobody will be emailed when a driver checks in here."
                    />
                )}
            />
        </>
    );
}

LocationCheckInOptionsPage.layout = [DashboardLayout, LocationLayout];
