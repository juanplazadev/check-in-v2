import { router, useForm } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';

import LocationUserController from '@/actions/App/Http/Controllers/Dashboard/LocationUserController';
import StaffPicker from '@/components/dashboard/location/StaffPicker';
import { SECONDARY_BUTTON } from '@/components/dashboard/RecordActionDialogs';
import type { LocationStaffMember } from '@/components/dashboard/types';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

/**
 * Attaches staff to a location. The candidates (location-scoped staff not attached yet) are an
 * optional prop, loaded by a partial reload when the dialog opens rather than on every visit.
 */
export default function AttachUsersDialog({
    open,
    onOpenChange,
    locationUuid,
    locationName,
    candidates,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    locationUuid: string;
    locationName: string;
    /** Undefined until the partial reload answers. */
    candidates: LocationStaffMember[] | undefined;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="dashboard sm:max-w-lg">
                {/* Radix unmounts the content on close, so each opening reloads the candidates. */}
                <AttachUsersBody
                    locationUuid={locationUuid}
                    locationName={locationName}
                    candidates={candidates}
                    onAttached={() => onOpenChange(false)}
                    onCancel={() => onOpenChange(false)}
                />
            </DialogContent>
        </Dialog>
    );
}

function AttachUsersBody({
    locationUuid,
    locationName,
    candidates,
    onAttached,
    onCancel,
}: {
    locationUuid: string;
    locationName: string;
    candidates: LocationStaffMember[] | undefined;
    onAttached: () => void;
    onCancel: () => void;
}) {
    const [loading, setLoading] = useState(true);
    const form = useForm<{ users: string[] }>({ users: [] });

    // Run from the mounted body: a parent setting open=true never fires onOpenChange.
    useEffect(() => {
        router.reload({
            only: ['attachableUsers'],
            onFinish: () => setLoading(false),
        });
    }, []);

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

        if (form.processing || form.data.users.length === 0) {
            return;
        }

        form.submit(LocationUserController.store(locationUuid), {
            preserveScroll: true,
            onSuccess: onAttached,
        });
    };

    const error =
        form.errors.users ??
        Object.entries(form.errors).find(([key]) =>
            key.startsWith('users.'),
        )?.[1];
    const count = form.data.users.length;

    return (
        <form onSubmit={submit} className="contents">
            <DialogHeader>
                <DialogTitle>Add users to {locationName}</DialogTitle>
                <DialogDescription>
                    They will see this location's check-ins and appointments on
                    the dashboard. Staff who already see every location are not
                    listed.
                </DialogDescription>
            </DialogHeader>

            {error !== undefined && (
                <div
                    role="alert"
                    className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600 dark:bg-red-500/10 dark:text-red-400"
                >
                    {error}
                </div>
            )}

            <StaffPicker
                candidates={loading ? candidates : (candidates ?? [])}
                selected={form.data.users}
                onToggle={toggle}
                emptyMessage="Everyone who can be added is already here."
            />

            <DialogFooter>
                <button
                    type="button"
                    onClick={onCancel}
                    disabled={form.processing}
                    className={SECONDARY_BUTTON}
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    disabled={form.processing || count === 0}
                    className="btn gap-2 bg-gray-900 text-gray-100 hover:bg-gray-800 disabled:opacity-50 dark:bg-gray-100 dark:text-gray-800 dark:hover:bg-white"
                >
                    <UserPlus className="size-4" aria-hidden />
                    {form.processing
                        ? 'Adding…'
                        : count > 1
                          ? `Add ${count} users`
                          : 'Add user'}
                </button>
            </DialogFooter>
        </form>
    );
}
