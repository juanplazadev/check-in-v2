import type { FormDataType } from '@inertiajs/core';
import { Head } from '@inertiajs/react';
import type { InertiaFormProps } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import { useState } from 'react';

import { update } from '@/actions/App/Http/Controllers/Dashboard/LocationDetailsController';
import { DetailRow } from '@/components/dashboard/DetailCard';
import EditableSection from '@/components/dashboard/location/EditableSection';
import LocationMap from '@/components/dashboard/location/LocationMap';
import SectionHeader from '@/components/dashboard/location/SectionHeader';
import type {
    DashboardLocationSummary,
    LocationDetails,
} from '@/components/dashboard/types';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { useCan } from '@/hooks/useCan';
import DashboardLayout from '@/layouts/DashboardLayout';
import LocationLayout from '@/layouts/LocationLayout';
import { formatDateTime, timezoneLabel } from '@/lib/dashboard/format';

interface LocationDetailsProps {
    location: DashboardLocationSummary;
    details: LocationDetails;
    /** The timezone select's options; empty for those who may not edit. */
    timezones: string[];
}

type Section = 'location' | 'contact' | 'radius';

const ROWS_CLASS = 'divide-y divide-gray-100 text-sm dark:divide-gray-700/60';

/** A labeled text input bound to one field of a section's form. */
function TextField<TData extends FormDataType<TData>>({
    form,
    name,
    label,
    className,
    ...props
}: {
    form: InertiaFormProps<TData>;
    name: keyof TData & string;
    label: string;
    className?: string;
} & Omit<ComponentProps<typeof Input>, 'form' | 'name'>) {
    const error = (form.errors as Record<string, string | undefined>)[name];

    return (
        <Field className={className} data-invalid={!!error || undefined}>
            <FieldLabel htmlFor={`location_${name}`}>{label}</FieldLabel>
            <Input
                id={`location_${name}`}
                value={String(form.data[name] ?? '')}
                aria-invalid={!!error || undefined}
                onChange={(event) =>
                    form.setData({ ...form.data, [name]: event.target.value })
                }
                {...props}
            />
            <FieldError>{error}</FieldError>
        </Field>
    );
}

export default function LocationDetailsPage({
    location,
    details,
    timezones,
}: LocationDetailsProps) {
    const { address } = details;
    const canEdit = useCan()('edit locations');
    const [editing, setEditing] = useState<Section | null>(null);

    const sectionProps = (section: Section) => ({
        action: update(location.uuid),
        canEdit,
        editing: editing === section,
        locked: editing !== null && editing !== section,
        onEditingChange: (next: boolean) => setEditing(next ? section : null),
    });

    return (
        <>
            <Head title={`${location.name} details`} />

            <SectionHeader
                title="Details"
                description="Contact information, address and where drivers can check in from."
            />

            <div className="grid gap-8 lg:grid-cols-2">
                <div>
                    <EditableSection
                        title="Location"
                        {...sectionProps('location')}
                        initialData={{
                            name: location.name,
                            abbreviation: location.abbreviation,
                            timezone: location.timezone,
                        }}
                        view={
                            <dl className={ROWS_CLASS}>
                                <DetailRow label="Name">
                                    {location.name}
                                </DetailRow>
                                <DetailRow
                                    label="Abbreviation"
                                    hint="Shown in the location's avatar."
                                >
                                    {location.abbreviation}
                                </DetailRow>
                                <DetailRow label="Timezone">
                                    {timezoneLabel(location.timezone)} time
                                </DetailRow>
                                <DetailRow label="Added">
                                    {formatDateTime(details.createdAt)}
                                </DetailRow>
                            </dl>
                        }
                        form={(form) => (
                            <div className="grid gap-4 sm:grid-cols-3">
                                <TextField
                                    form={form}
                                    name="name"
                                    label="Name"
                                    className="sm:col-span-2"
                                    maxLength={100}
                                    autoFocus
                                />
                                <TextField
                                    form={form}
                                    name="abbreviation"
                                    label="Abbreviation"
                                    maxLength={4}
                                    className="uppercase"
                                />
                                <Field
                                    className="sm:col-span-3"
                                    data-invalid={
                                        !!form.errors.timezone || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="location_timezone">
                                        Timezone
                                    </FieldLabel>
                                    <select
                                        id="location_timezone"
                                        value={form.data.timezone}
                                        onChange={(event) =>
                                            form.setData(
                                                'timezone',
                                                event.target.value,
                                            )
                                        }
                                        className="form-select w-full bg-white dark:bg-gray-800"
                                    >
                                        {timezones.map((timezone) => (
                                            <option
                                                key={timezone}
                                                value={timezone}
                                            >
                                                {timezoneLabel(timezone)} (
                                                {timezone})
                                            </option>
                                        ))}
                                    </select>
                                    <FieldError>
                                        {form.errors.timezone}
                                    </FieldError>
                                </Field>
                            </div>
                        )}
                    />

                    <EditableSection
                        title="Contact"
                        {...sectionProps('contact')}
                        initialData={{
                            phone: location.phone,
                            phone_ext: location.phoneExt ?? '',
                            email: details.email,
                            street1: address.street1,
                            street2: address.street2 ?? '',
                            city: address.city,
                            state: address.state,
                            zip_code: address.zipCode,
                            zip_code_4: address.zipCode4,
                            country: address.country,
                        }}
                        view={
                            <dl className={ROWS_CLASS}>
                                <DetailRow label="Phone">
                                    {location.phone}
                                    {location.phoneExt &&
                                        ` ext. ${location.phoneExt}`}
                                </DetailRow>
                                <DetailRow label="Email">
                                    {details.email}
                                </DetailRow>
                                <DetailRow label="Address">
                                    <div>{address.street1}</div>
                                    {address.street2 && (
                                        <div>{address.street2}</div>
                                    )}
                                    <div>
                                        {address.city}, {address.state}{' '}
                                        {address.zipCode}
                                        {address.zipCode4 &&
                                            `-${address.zipCode4}`}
                                    </div>
                                    <div>{address.country}</div>
                                </DetailRow>
                            </dl>
                        }
                        form={(form) => (
                            <div className="grid gap-4 sm:grid-cols-6">
                                <TextField
                                    form={form}
                                    name="phone"
                                    label="Phone"
                                    className="sm:col-span-4"
                                    type="tel"
                                    inputMode="tel"
                                    maxLength={20}
                                    autoFocus
                                />
                                <TextField
                                    form={form}
                                    name="phone_ext"
                                    label="Ext."
                                    className="sm:col-span-2"
                                    inputMode="numeric"
                                    maxLength={10}
                                />
                                <TextField
                                    form={form}
                                    name="email"
                                    label="Email"
                                    className="sm:col-span-6"
                                    type="email"
                                    maxLength={255}
                                />
                                <TextField
                                    form={form}
                                    name="street1"
                                    label="Street"
                                    className="sm:col-span-6"
                                    maxLength={255}
                                />
                                <TextField
                                    form={form}
                                    name="street2"
                                    label="Street line 2"
                                    className="sm:col-span-6"
                                    maxLength={255}
                                    placeholder="Optional"
                                />
                                <TextField
                                    form={form}
                                    name="city"
                                    label="City"
                                    className="sm:col-span-4"
                                    maxLength={100}
                                />
                                <TextField
                                    form={form}
                                    name="state"
                                    label="State"
                                    className="uppercase sm:col-span-2"
                                    maxLength={2}
                                />
                                <TextField
                                    form={form}
                                    name="zip_code"
                                    label="ZIP code"
                                    className="sm:col-span-2"
                                    inputMode="numeric"
                                    maxLength={5}
                                />
                                <TextField
                                    form={form}
                                    name="zip_code_4"
                                    label="ZIP+4"
                                    className="sm:col-span-2"
                                    inputMode="numeric"
                                    maxLength={4}
                                />
                                <TextField
                                    form={form}
                                    name="country"
                                    label="Country"
                                    className="uppercase sm:col-span-2"
                                    maxLength={2}
                                />
                            </div>
                        )}
                    />
                </div>

                <EditableSection
                    title="Map & check-in radius"
                    {...sectionProps('radius')}
                    initialData={{
                        max_distance_allowed: details.map.maxDistanceAllowed,
                    }}
                    view={
                        <>
                            <LocationMap point={details.map} className="h-80" />
                            <p className="mt-3 text-xs text-gray-500 dark:text-gray-400">
                                Drivers can check in from within the circle.
                            </p>
                        </>
                    }
                    form={(form) => {
                        const radius = Number(form.data.max_distance_allowed);

                        return (
                            <>
                                {/* The circle follows the input, so the admin sees the reach before saving. */}
                                <LocationMap
                                    point={{
                                        ...details.map,
                                        maxDistanceAllowed:
                                            Number.isFinite(radius) &&
                                            radius > 0
                                                ? radius
                                                : details.map
                                                      .maxDistanceAllowed,
                                    }}
                                    className="h-80"
                                />
                                <TextField
                                    form={form}
                                    name="max_distance_allowed"
                                    label="Check-in radius (miles)"
                                    type="number"
                                    inputMode="numeric"
                                    min={1}
                                    max={500}
                                    step={1}
                                    autoFocus
                                />
                            </>
                        );
                    }}
                />
            </div>
        </>
    );
}

LocationDetailsPage.layout = [DashboardLayout, LocationLayout];
