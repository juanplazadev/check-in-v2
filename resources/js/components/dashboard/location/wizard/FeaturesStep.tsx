import { useForm } from '@inertiajs/react';
import { CalendarClock, Truck } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';

import { features } from '@/actions/App/Http/Controllers/Dashboard/LocationWizardController';
import WizardFooter from '@/components/dashboard/location/wizard/WizardFooter';
import type { LocationWizardDraft } from '@/components/dashboard/types';
import { Switch } from '@/components/ui/switch';

function FeatureCard({
    id,
    icon,
    title,
    description,
    checked,
    onCheckedChange,
}: {
    id: string;
    icon: ReactNode;
    title: string;
    description: string;
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
}) {
    return (
        <label
            htmlFor={id}
            className={`flex cursor-pointer items-start gap-4 rounded-lg border bg-white p-4 shadow-sm transition dark:bg-gray-800 ${
                checked
                    ? 'border-brand-green'
                    : 'border-gray-200 hover:border-gray-300 dark:border-gray-700/60 dark:hover:border-gray-600'
            }`}
        >
            <span className="mt-0.5 text-gray-500 dark:text-gray-400">
                {icon}
            </span>
            <span className="min-w-0 flex-1">
                <span className="block text-sm font-semibold text-gray-800 dark:text-gray-100">
                    {title}
                </span>
                <span className="mt-0.5 block text-sm text-gray-500 dark:text-gray-400">
                    {description}
                </span>
            </span>
            <Switch
                id={id}
                checked={checked}
                onCheckedChange={onCheckedChange}
            />
        </label>
    );
}

export default function FeaturesStep({
    draft,
}: {
    draft: LocationWizardDraft['features'];
}) {
    const form = useForm({
        is_checkins_enabled: draft?.is_checkins_enabled ?? true,
        is_appointments_enabled: draft?.is_appointments_enabled ?? true,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(features(), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} noValidate>
            <div className="space-y-3">
                <FeatureCard
                    id="location_is_checkins_enabled"
                    icon={<Truck className="size-5" aria-hidden />}
                    title="Check-ins"
                    description="Drivers check in on arrival from their phone."
                    checked={form.data.is_checkins_enabled}
                    onCheckedChange={(checked) => {
                        form.setData('is_checkins_enabled', checked);
                        form.clearErrors();
                    }}
                />
                <FeatureCard
                    id="location_is_appointments_enabled"
                    icon={<CalendarClock className="size-5" aria-hidden />}
                    title="Appointments"
                    description="Drivers book a time slot ahead of their visit."
                    checked={form.data.is_appointments_enabled}
                    onCheckedChange={(checked) => {
                        form.setData('is_appointments_enabled', checked);
                        form.clearErrors();
                    }}
                />
            </div>
            {form.errors.is_checkins_enabled && (
                <p
                    role="alert"
                    className="mt-3 text-sm font-medium text-red-500"
                >
                    {form.errors.is_checkins_enabled}
                </p>
            )}
            <p className="mt-4 text-xs text-gray-500 dark:text-gray-400">
                You'll set the hours for each feature next. Options like the
                check-in form fields can be changed on the location's pages
                afterwards.
            </p>

            <WizardFooter previous="placement" processing={form.processing} />
        </form>
    );
}
