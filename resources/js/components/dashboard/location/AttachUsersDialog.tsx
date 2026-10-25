import { router, useForm } from '@inertiajs/react';
import { Check, Search, UserPlus } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useMemo, useState } from 'react';

import LocationUserController from '@/actions/App/Http/Controllers/Dashboard/LocationUserController';
import { SECONDARY_BUTTON } from '@/components/dashboard/RecordActionDialogs';
import RoleChips from '@/components/dashboard/RoleChips';
import type { LocationStaffMember } from '@/components/dashboard/types';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { initialsOf } from '@/lib/dashboard/format';

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
    const [search, setSearch] = useState('');
    const form = useForm<{ users: string[] }>({ users: [] });

    // Run from the mounted body: a parent setting open=true never fires onOpenChange.
    useEffect(() => {
        router.reload({
            only: ['attachableUsers'],
            onFinish: () => setLoading(false),
        });
    }, []);

    const filtered = useMemo(() => {
        const term = search.trim().toLowerCase();

        return (candidates ?? []).filter(
            (member) =>
                term === '' ||
                member.name.toLowerCase().includes(term) ||
                member.email.toLowerCase().includes(term),
        );
    }, [candidates, search]);

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

            <div className="relative">
                <Search
                    aria-hidden
                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-gray-400"
                />
                <Input
                    type="search"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    placeholder="Search by name or email"
                    aria-label="Search users"
                    className="pl-9"
                    autoFocus
                />
            </div>

            <div className="max-h-80 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700/60">
                {loading && candidates === undefined ? (
                    <div role="status" className="space-y-2 p-3">
                        <span className="sr-only">Loading users…</span>
                        <div className="h-12 animate-pulse rounded-lg bg-gray-100 dark:bg-gray-700/40" />
                        <div className="h-12 animate-pulse rounded-lg bg-gray-100 dark:bg-gray-700/40" />
                        <div className="h-12 animate-pulse rounded-lg bg-gray-100 dark:bg-gray-700/40" />
                    </div>
                ) : filtered.length === 0 ? (
                    <p className="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                        {(candidates ?? []).length === 0
                            ? 'Everyone who can be added is already here.'
                            : 'No users match your search.'}
                    </p>
                ) : (
                    <ul className="divide-y divide-gray-100 dark:divide-gray-700/60">
                        {filtered.map((member) => {
                            const selected = form.data.users.includes(
                                member.uuid,
                            );

                            return (
                                <li key={member.uuid}>
                                    <button
                                        type="button"
                                        role="checkbox"
                                        aria-checked={selected}
                                        onClick={() => toggle(member.uuid)}
                                        className={`flex w-full cursor-pointer items-center gap-3 px-3 py-2.5 text-left ${
                                            selected
                                                ? 'bg-brand-green/[0.06] dark:bg-brand-green/[0.12]'
                                                : 'hover:bg-gray-50 dark:hover:bg-gray-700/30'
                                        }`}
                                    >
                                        <span
                                            className={`flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold ${
                                                selected
                                                    ? 'bg-brand-green text-white'
                                                    : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'
                                            }`}
                                        >
                                            {selected ? (
                                                <Check
                                                    className="size-4"
                                                    aria-hidden
                                                />
                                            ) : (
                                                initialsOf(member.name)
                                            )}
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate text-sm font-medium text-gray-800 dark:text-gray-100">
                                                {member.name}
                                            </span>
                                            <span className="block truncate text-xs text-gray-500 dark:text-gray-400">
                                                {member.email}
                                            </span>
                                        </span>
                                        <RoleChips roles={member.roles} />
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>

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
