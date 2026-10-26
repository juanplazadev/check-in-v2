import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { placement } from '@/actions/App/Http/Controllers/Dashboard/LocationWizardController';
import LocationMap from '@/components/dashboard/location/LocationMap';
import TextField from '@/components/dashboard/location/TextField';
import WizardFooter from '@/components/dashboard/location/wizard/WizardFooter';
import type { LocationWizardDraft } from '@/components/dashboard/types';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { timezoneLabel } from '@/lib/dashboard/format';

export default function PlacementStep({
    address,
    draft,
    timezones,
    defaultMaxDistance,
}: {
    address: NonNullable<LocationWizardDraft['address']>;
    draft: LocationWizardDraft['placement'];
    timezones: string[];
    defaultMaxDistance: number;
}) {
    const form = useForm({
        timezone: draft?.timezone ?? address.timezone ?? '',
        max_distance_allowed: String(
            draft?.max_distance_allowed ?? defaultMaxDistance,
        ),
    });

    const radius = Number(form.data.max_distance_allowed);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(placement(), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} noValidate>
            {/* The circle follows the input, so the admin sees the reach before saving. */}
            <LocationMap
                point={{
                    latitude: address.latitude,
                    longitude: address.longitude,
                    maxDistanceAllowed:
                        Number.isFinite(radius) && radius > 0
                            ? radius
                            : defaultMaxDistance,
                }}
                className="h-72"
            />
            <p className="mt-2 mb-6 text-xs text-gray-500 dark:text-gray-400">
                {address.street1}, {address.city}, {address.state}{' '}
                {address.zip_code}. Drivers can check in from within the circle.
            </p>

            <div className="grid gap-4 sm:grid-cols-6">
                <Field
                    className="sm:col-span-4"
                    data-invalid={!!form.errors.timezone || undefined}
                >
                    <FieldLabel htmlFor="location_timezone">
                        Timezone
                    </FieldLabel>
                    <select
                        id="location_timezone"
                        value={form.data.timezone}
                        onChange={(event) =>
                            form.setData('timezone', event.target.value)
                        }
                        aria-invalid={!!form.errors.timezone || undefined}
                        className="form-select w-full bg-white dark:bg-gray-800"
                    >
                        <option value="" disabled>
                            Choose a timezone
                        </option>
                        {timezones.map((timezone) => (
                            <option key={timezone} value={timezone}>
                                {timezoneLabel(timezone)} ({timezone})
                            </option>
                        ))}
                    </select>
                    {address.timezone === null && !form.errors.timezone && (
                        <p className="text-xs text-amber-700 dark:text-amber-400">
                            We couldn't detect the timezone, please choose one.
                        </p>
                    )}
                    <FieldError>{form.errors.timezone}</FieldError>
                </Field>
                <TextField
                    form={form}
                    name="max_distance_allowed"
                    label="Check-in radius (miles)"
                    className="sm:col-span-2"
                    type="number"
                    inputMode="numeric"
                    min={1}
                    max={500}
                    step={1}
                />
            </div>

            <WizardFooter previous="address" processing={form.processing} />
        </form>
    );
}
