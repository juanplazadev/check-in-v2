import { useForm } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import type { FormEvent } from 'react';

import { users } from '@/actions/App/Http/Controllers/Dashboard/LocationWizardController';
import StaffPicker from '@/components/dashboard/location/StaffPicker';
import WizardFooter from '@/components/dashboard/location/wizard/WizardFooter';
import { SECONDARY_BUTTON } from '@/components/dashboard/RecordActionDialogs';
import type {
    LocationStaffMember,
    LocationWizardDraft,
} from '@/components/dashboard/types';

/** Shown whenever the new location would have nobody attached. */
export function NoUsersWarning() {
    return (
        <div
            role="alert"
            className="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-800/40 dark:bg-amber-900/20 dark:text-amber-300"
        >
            <TriangleAlert className="mt-0.5 size-4 shrink-0" aria-hidden />
            <p>
                <span className="font-medium">No users will be added.</span>{' '}
                Until someone is added, only admins and other staff who see
                every location will see this location, its check-ins and its
                appointments. You can add users later from its Users page.
            </p>
        </div>
    );
}

export default function UsersStep({
    draft,
    staff,
}: {
    draft: LocationWizardDraft['users'];
    staff: LocationStaffMember[];
}) {
    const form = useForm<{ users: string[] }>({
        users: draft?.users ?? [],
    });

    const toggle = (uuid: string) => {
        form.setData(
            'users',
            form.data.users.includes(uuid)
                ? form.data.users.filter((selected) => selected !== uuid)
                : [...form.data.users, uuid],
        );
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(users(), { preserveScroll: true });
    };

    const skip = () => {
        form.transform(() => ({ users: [] }));
        form.submit(users(), {
            preserveScroll: true,
            onFinish: () => form.transform((data) => data),
        });
    };

    const error =
        form.errors.users ??
        Object.entries(form.errors).find(([key]) =>
            key.startsWith('users.'),
        )?.[1];
    const count = form.data.users.length;

    return (
        <form onSubmit={submit} noValidate className="space-y-4">
            <p className="text-sm text-gray-500 dark:text-gray-400">
                Pick the staff who work here. They will see this location's
                check-ins and appointments on the dashboard. Staff who already
                see every location are not listed.
            </p>

            {error !== undefined && (
                <div
                    role="alert"
                    className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600 dark:bg-red-500/10 dark:text-red-400"
                >
                    {error}
                </div>
            )}

            <StaffPicker
                candidates={staff}
                selected={form.data.users}
                onToggle={toggle}
                emptyMessage="There is no location-scoped staff to add yet."
            />

            {count === 0 && <NoUsersWarning />}

            <WizardFooter
                previous="features"
                processing={form.processing}
                label={
                    count === 0
                        ? 'Continue without users'
                        : `Add ${count} ${count === 1 ? 'user' : 'users'}`
                }
                extra={
                    count > 0 && (
                        <button
                            type="button"
                            onClick={skip}
                            disabled={form.processing}
                            className={SECONDARY_BUTTON}
                        >
                            Skip for now
                        </button>
                    )
                }
            />
        </form>
    );
}
