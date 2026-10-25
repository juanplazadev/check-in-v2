import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

import { destroy } from '@/actions/App/Http/Controllers/Dashboard/LocationWizardController';
import BackLink from '@/components/dashboard/BackLink';
import type { DayDraft } from '@/components/dashboard/location/WeeklyHoursEditor';
import AddressStep from '@/components/dashboard/location/wizard/AddressStep';
import DetailsStep from '@/components/dashboard/location/wizard/DetailsStep';
import FeaturesStep from '@/components/dashboard/location/wizard/FeaturesStep';
import HoursStep from '@/components/dashboard/location/wizard/HoursStep';
import PlacementStep from '@/components/dashboard/location/wizard/PlacementStep';
import ReviewStep from '@/components/dashboard/location/wizard/ReviewStep';
import UsersStep from '@/components/dashboard/location/wizard/UsersStep';
import WizardProgress from '@/components/dashboard/location/wizard/WizardProgress';
import PageHeader from '@/components/dashboard/PageHeader';
import {
    SECONDARY_BUTTON,
    StatusConfirmDialog,
} from '@/components/dashboard/RecordActionDialogs';
import RequirePermission from '@/components/dashboard/RequirePermission';
import type {
    LocationStaffMember,
    LocationWizardDraft,
    LocationWizardStep,
    LocationWizardStepValue,
} from '@/components/dashboard/types';
import DashboardLayout from '@/layouts/DashboardLayout';
import dashboardRoutes from '@/routes/dashboard';

interface LocationsCreateProps {
    step: LocationWizardStepValue;
    steps: LocationWizardStep[];
    /** The answers saved so far. The server only renders a step once every step before it is saved. */
    draft: LocationWizardDraft;
    /** The timezone select's options; only sent on the placement step. */
    timezones: string[];
    defaultMaxDistance: number;
    /** The week the hours step starts from; only sent on the hours step. */
    defaultHours: DayDraft[];
    /** Staff who can be added; only sent on the users and review steps. */
    staff: LocationStaffMember[];
    /** The code made from the name when the abbreviation was left blank; review step only. */
    abbreviationPreview: string | null;
}

const DESCRIPTIONS: Record<LocationWizardStepValue, string> = {
    details: 'What the location is called and how to reach it.',
    address: 'Where the dock is.',
    placement: 'Confirm the timezone and how far away drivers can check in.',
    features: 'Choose what drivers can do here.',
    hours: 'Set the weekly hours drivers can check in or book.',
    users: 'Add the staff who work at this location.',
    review: 'Check everything over, then create the location.',
};

export default function LocationsCreate({
    step,
    steps,
    draft,
    timezones,
    defaultMaxDistance,
    defaultHours,
    staff,
    abbreviationPreview,
}: LocationsCreateProps) {
    const [discarding, setDiscarding] = useState(false);
    const [busy, setBusy] = useState(false);
    const current = steps.find((each) => each.value === step);
    const hasDraft = steps.some((each) => each.completed);

    const discard = () => {
        router.delete(destroy.url(), {
            onStart: () => setBusy(true),
            onFinish: () => setBusy(false),
        });
    };

    return (
        <RequirePermission permission="create locations">
            <Head title="Add location" />

            <BackLink
                href={dashboardRoutes.locations.url()}
                label="Locations"
            />

            <PageHeader
                title="Add location"
                subtitle={DESCRIPTIONS[step]}
                actions={
                    hasDraft && (
                        <button
                            type="button"
                            onClick={() => setDiscarding(true)}
                            className={SECONDARY_BUTTON}
                        >
                            Discard draft
                        </button>
                    )
                }
            />

            <div className="mx-auto max-w-2xl">
                <WizardProgress steps={steps} current={step} />

                <div className="rounded-xl bg-white p-5 shadow-sm sm:p-6 dark:bg-gray-800">
                    <h2 className="mb-5 text-lg font-semibold text-gray-800 dark:text-gray-100">
                        {current?.label}
                    </h2>

                    {step === 'details' && (
                        <DetailsStep draft={draft.details} />
                    )}
                    {step === 'address' && (
                        <AddressStep draft={draft.address} />
                    )}
                    {step === 'placement' && draft.address && (
                        <PlacementStep
                            address={draft.address}
                            draft={draft.placement}
                            timezones={timezones}
                            defaultMaxDistance={defaultMaxDistance}
                        />
                    )}
                    {step === 'features' && (
                        <FeaturesStep draft={draft.features} />
                    )}
                    {step === 'hours' && draft.features && (
                        <HoursStep
                            features={draft.features}
                            draft={draft.hours}
                            defaultHours={defaultHours}
                        />
                    )}
                    {step === 'users' && (
                        <UsersStep draft={draft.users} staff={staff} />
                    )}
                    {step === 'review' && (
                        <ReviewStep
                            draft={draft as Required<LocationWizardDraft>}
                            staff={staff}
                            abbreviationPreview={abbreviationPreview}
                        />
                    )}
                </div>
            </div>

            <StatusConfirmDialog
                open={discarding}
                onOpenChange={setDiscarding}
                title="Discard this location?"
                description="Everything entered so far is thrown away. Nothing has been created yet."
                keepLabel="Keep editing"
                confirmLabel="Discard"
                busy={busy}
                onConfirm={discard}
            />
        </RequirePermission>
    );
}

LocationsCreate.layout = DashboardLayout;
