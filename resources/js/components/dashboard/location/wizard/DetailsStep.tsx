import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { details } from '@/actions/App/Http/Controllers/Dashboard/LocationWizardController';
import TextField from '@/components/dashboard/location/TextField';
import WizardFooter from '@/components/dashboard/location/wizard/WizardFooter';
import type { LocationWizardDraft } from '@/components/dashboard/types';

export default function DetailsStep({
    draft,
}: {
    draft: LocationWizardDraft['details'];
}) {
    const form = useForm({
        name: draft?.name ?? '',
        email: draft?.email ?? '',
        phone: draft?.phone ?? '',
        phone_ext: draft?.phone_ext ?? '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(details(), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} noValidate>
            <div className="grid gap-4 sm:grid-cols-6">
                <TextField
                    form={form}
                    name="name"
                    label="Name"
                    className="sm:col-span-6"
                    maxLength={100}
                    autoFocus
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
                    name="phone"
                    label="Phone"
                    className="sm:col-span-4"
                    type="tel"
                    inputMode="tel"
                    maxLength={20}
                />
                <TextField
                    form={form}
                    name="phone_ext"
                    label="Ext."
                    className="sm:col-span-2"
                    inputMode="numeric"
                    maxLength={10}
                    placeholder="Optional"
                />
            </div>

            <WizardFooter processing={form.processing} />
        </form>
    );
}
