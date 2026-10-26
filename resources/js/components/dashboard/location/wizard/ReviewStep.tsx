import { Link, router } from '@inertiajs/react';
import { Check } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';

import {
    show,
    store,
} from '@/actions/App/Http/Controllers/Dashboard/LocationWizardController';
import { DetailRow } from '@/components/dashboard/DetailCard';
import { NoUsersWarning } from '@/components/dashboard/location/wizard/UsersStep';
import WizardFooter from '@/components/dashboard/location/wizard/WizardFooter';
import type {
    LocationStaffMember,
    LocationWizardDraft,
    LocationWizardHoursDay,
    LocationWizardStepValue,
} from '@/components/dashboard/types';
import { timezoneLabel } from '@/lib/dashboard/format';
import { summarizeWeek } from '@/lib/dashboard/hours';

function weekSummary(days: LocationWizardHoursDay[] | undefined): string {
    return summarizeWeek(
        (days ?? []).map((day, dayOfWeek) => ({
            dayOfWeek,
            openTime: day.is_open ? day.open_time : null,
            closeTime: day.is_open ? day.close_time : null,
        })),
    ).join(' · ');
}

function ReviewSection({
    title,
    step,
    children,
}: {
    title: string;
    step: LocationWizardStepValue;
    children: ReactNode;
}) {
    return (
        <section className="border-b border-gray-100 py-4 first:pt-0 dark:border-gray-700/60">
            <div className="mb-2 flex items-center justify-between">
                <h2 className="text-sm font-semibold text-gray-800 dark:text-gray-100">
                    {title}
                </h2>
                <Link
                    href={show.url(step)}
                    className="text-sm font-medium text-brand-green hover:underline"
                >
                    Edit
                </Link>
            </div>
            <dl className="divide-y divide-gray-100 text-sm dark:divide-gray-700/60">
                {children}
            </dl>
        </section>
    );
}

export default function ReviewStep({
    draft,
    staff,
    abbreviationPreview,
}: {
    draft: Required<LocationWizardDraft>;
    staff: LocationStaffMember[];
    abbreviationPreview: string | null;
}) {
    const [processing, setProcessing] = useState(false);
    const { details, address, placement, features, hours } = draft;
    const picked = staff.filter((member) =>
        draft.users.users.includes(member.uuid),
    );

    const create = () => {
        router.post(
            store.url(),
            {},
            {
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
            },
        );
    };

    const enabled = [
        features.is_checkins_enabled && 'Check-ins',
        features.is_appointments_enabled && 'Appointments',
    ].filter(Boolean);

    return (
        <div>
            <ReviewSection title="Details" step="details">
                <DetailRow label="Name">{details.name}</DetailRow>
                <DetailRow label="Abbreviation">
                    {abbreviationPreview}
                    <span className="block text-xs font-normal text-gray-500 dark:text-gray-400">
                        Generated from the name. You can change it later on the
                        Details page.
                    </span>
                </DetailRow>
                <DetailRow label="Email">{details.email}</DetailRow>
                <DetailRow label="Phone">
                    {details.phone}
                    {details.phone_ext && ` ext. ${details.phone_ext}`}
                </DetailRow>
            </ReviewSection>

            <ReviewSection title="Address" step="address">
                <DetailRow label="Address">
                    <div>{address.street1}</div>
                    {address.street2 && <div>{address.street2}</div>}
                    <div>
                        {address.city}, {address.state} {address.zip_code}-
                        {address.zip_code_4}
                    </div>
                </DetailRow>
            </ReviewSection>

            <ReviewSection title="Map & timezone" step="placement">
                <DetailRow label="Timezone">
                    {timezoneLabel(placement.timezone)} time
                </DetailRow>
                <DetailRow label="Check-in radius">
                    {placement.max_distance_allowed} miles
                </DetailRow>
            </ReviewSection>

            <ReviewSection title="Features" step="features">
                <DetailRow label="Turned on">
                    {enabled.length > 0 ? enabled.join(' and ') : 'None'}
                </DetailRow>
            </ReviewSection>

            <ReviewSection title="Hours" step="hours">
                {features.is_checkins_enabled && (
                    <DetailRow label="Check-ins">
                        {weekSummary(hours.checkins)}
                    </DetailRow>
                )}
                {features.is_appointments_enabled && (
                    <DetailRow label="Appointments">
                        {weekSummary(hours.appointments)}
                    </DetailRow>
                )}
            </ReviewSection>

            <ReviewSection title="Users" step="users">
                <DetailRow label="Added">
                    {picked.length > 0
                        ? picked.map((member) => member.name).join(', ')
                        : 'Nobody yet'}
                </DetailRow>
            </ReviewSection>

            {picked.length === 0 && (
                <div className="mt-4">
                    <NoUsersWarning />
                </div>
            )}

            <WizardFooter
                previous="users"
                processing={processing}
                processingLabel="Creating…"
                label="Create location"
                icon={<Check className="size-4" aria-hidden />}
                type="button"
                onClick={create}
            />
        </div>
    );
}
