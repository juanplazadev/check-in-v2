import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { address } from '@/actions/App/Http/Controllers/Dashboard/LocationWizardController';
import TextField from '@/components/dashboard/location/TextField';
import WizardFooter from '@/components/dashboard/location/wizard/WizardFooter';
import type { LocationWizardDraft } from '@/components/dashboard/types';

export default function AddressStep({
    draft,
}: {
    draft: LocationWizardDraft['address'];
}) {
    const form = useForm({
        street1: draft?.street1 ?? '',
        street2: draft?.street2 ?? '',
        city: draft?.city ?? '',
        state: draft?.state ?? '',
        zip_code: draft?.zip_code ?? '',
        zip_code_4: draft?.zip_code_4 ?? '',
        country: 'US',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(address(), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} noValidate>
            <p className="mb-4 text-sm text-gray-500 dark:text-gray-400">
                We look the address up to place it on the map and suggest its
                timezone. US addresses only.
            </p>
            <div className="grid gap-4 sm:grid-cols-6">
                <TextField
                    form={form}
                    name="street1"
                    label="Street"
                    className="sm:col-span-6"
                    maxLength={255}
                    autoComplete="address-line1"
                    autoFocus
                />
                <TextField
                    form={form}
                    name="street2"
                    label="Street line 2"
                    className="sm:col-span-6"
                    maxLength={255}
                    autoComplete="address-line2"
                    placeholder="Optional"
                />
                <TextField
                    form={form}
                    name="city"
                    label="City"
                    className="sm:col-span-4"
                    maxLength={100}
                    autoComplete="address-level2"
                />
                <TextField
                    form={form}
                    name="state"
                    label="State"
                    className="uppercase sm:col-span-2"
                    maxLength={2}
                    autoComplete="address-level1"
                />
                <TextField
                    form={form}
                    name="zip_code"
                    label="ZIP code"
                    className="sm:col-span-3"
                    inputMode="numeric"
                    maxLength={5}
                    autoComplete="postal-code"
                />
                <TextField
                    form={form}
                    name="zip_code_4"
                    label="ZIP+4"
                    className="sm:col-span-3"
                    inputMode="numeric"
                    maxLength={4}
                />
            </div>

            <WizardFooter
                previous="details"
                processing={form.processing}
                processingLabel="Looking it up…"
            />
        </form>
    );
}
