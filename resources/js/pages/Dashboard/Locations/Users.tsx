import { Head, Link, router } from '@inertiajs/react';
import { ChevronDown, Globe, Search, UserPlus, Users } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';

import LocationUserController from '@/actions/App/Http/Controllers/Dashboard/LocationUserController';
import DataTablePagination from '@/components/dashboard/data-table/DataTablePagination';
import AttachUsersDialog from '@/components/dashboard/location/AttachUsersDialog';
import SectionHeader, {
    SectionGroup,
} from '@/components/dashboard/location/SectionHeader';
import { StatusConfirmDialog } from '@/components/dashboard/RecordActionDialogs';
import RoleChips from '@/components/dashboard/RoleChips';
import type {
    DashboardLocationSummary,
    LocationStaffMember,
    LocationUsers,
} from '@/components/dashboard/types';
import { useCan } from '@/hooks/useCan';
import DashboardLayout from '@/layouts/DashboardLayout';
import LocationLayout from '@/layouts/LocationLayout';
import { initialsOf } from '@/lib/dashboard/format';
import dashboardRoutes from '@/routes/dashboard';

interface LocationUsersProps {
    location: DashboardLocationSummary;
    users: LocationUsers;
    /** The attached list's search, as the server applied it. */
    search: string | null;
    /** Optional prop: only present after the add dialog's partial reload. */
    attachableUsers?: LocationStaffMember[];
}

function MemberRow({
    member,
    onRemove,
}: {
    member: LocationStaffMember;
    /** Absent when the viewer may not detach. */
    onRemove?: () => void;
}) {
    const can = useCan();
    const identity = (
        <>
            <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                {initialsOf(member.name)}
            </span>
            <span className="min-w-0 flex-1">
                <span className="block truncate font-medium text-gray-800 group-hover:text-brand-green dark:text-gray-100">
                    {member.name}
                </span>
                <span className="block truncate text-sm text-gray-500 dark:text-gray-400">
                    {member.email}
                </span>
            </span>
        </>
    );

    return (
        <li className="flex items-center gap-3 py-3">
            {can('view users') ? (
                <Link
                    href={dashboardRoutes.users.show.url(member.uuid)}
                    className="group flex min-w-0 flex-1 items-center gap-3"
                >
                    {identity}
                </Link>
            ) : (
                <div className="flex min-w-0 flex-1 items-center gap-3">
                    {identity}
                </div>
            )}
            <span className="hidden sm:block">
                <RoleChips roles={member.roles} />
            </span>
            {onRemove && (
                <button
                    type="button"
                    onClick={onRemove}
                    className="btn-sm shrink-0 border-gray-200 text-gray-600 hover:border-rose-300 hover:text-rose-600 dark:border-gray-700/60 dark:text-gray-300 dark:hover:border-rose-500/60 dark:hover:text-rose-400"
                >
                    Remove
                </button>
            )}
        </li>
    );
}

const SEARCH_DEBOUNCE_MS = 300;

function queryFor(search: string, page = 1): Record<string, string | number> {
    return {
        ...(search.trim() !== '' && { search: search.trim() }),
        ...(page > 1 && { page }),
    };
}

function AttachedSearch({
    value,
    onChange,
}: {
    value: string;
    onChange: (value: string) => void;
}) {
    const id = useId();

    return (
        <div className="relative mb-2">
            <label htmlFor={id} className="sr-only">
                Search attached users
            </label>
            <input
                id={id}
                className="form-input w-full bg-white pl-9 sm:w-64 dark:bg-gray-800"
                type="search"
                placeholder="Search by name or email"
                value={value}
                onChange={(event) => onChange(event.target.value)}
            />
            <div className="pointer-events-none absolute inset-0 right-auto flex items-center">
                <Search className="mr-2 ml-3 size-4 shrink-0 text-gray-400 dark:text-gray-500" />
            </div>
        </div>
    );
}

function EmptyAttached() {
    return (
        <div className="flex items-start gap-4 rounded-lg border border-dashed border-gray-200 p-5 dark:border-gray-700/60">
            <span className="flex size-11 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-500 dark:bg-gray-700/60 dark:text-gray-400">
                <Users className="size-5" aria-hidden />
            </span>
            <div className="min-w-0">
                <h4 className="font-semibold text-gray-800 dark:text-gray-100">
                    No users attached
                </h4>
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Shipping staff see a location's check-ins and appointments
                    only once they are added here.
                </p>
            </div>
        </div>
    );
}

export default function LocationUsersPage({
    location,
    users,
    search,
    attachableUsers,
}: LocationUsersProps) {
    const can = useCan();
    const canManage = can('manage location users');
    const [adding, setAdding] = useState(false);
    const [removing, setRemoving] = useState<LocationStaffMember | null>(null);
    const [busy, setBusy] = useState(false);
    const [showAllLocations, setShowAllLocations] = useState(false);
    const [term, setTerm] = useState(search ?? '');
    const isFirstRun = useRef(true);
    const isSearching = (search ?? '') !== '';

    useEffect(() => {
        if (isFirstRun.current) {
            isFirstRun.current = false;

            return;
        }

        const timer = window.setTimeout(
            () =>
                router.get(
                    dashboardRoutes.locations.users.url(location.uuid),
                    queryFor(term),
                    {
                        only: ['users', 'search'],
                        preserveState: true,
                        preserveScroll: true,
                        replace: true,
                    },
                ),
            SEARCH_DEBOUNCE_MS,
        );

        return () => window.clearTimeout(timer);
    }, [term, location.uuid]);

    const changePage = (pageIndex: number) =>
        router.get(
            dashboardRoutes.locations.users.url(location.uuid),
            queryFor(search ?? '', pageIndex + 1),
            {
                only: ['users', 'search'],
                preserveState: true,
                preserveScroll: true,
            },
        );

    const remove = () => {
        if (removing === null) {
            return;
        }

        router.visit(
            LocationUserController.destroy({
                uuid: location.uuid,
                userUuid: removing.uuid,
            }),
            {
                preserveScroll: true,
                onStart: () => setBusy(true),
                onSuccess: () => setRemoving(null),
                onFinish: () => setBusy(false),
            },
        );
    };

    return (
        <>
            <Head title={`${location.name} users`} />

            <SectionHeader
                title="Users"
                description="Who works at this location and sees its check-ins and appointments."
                action={
                    canManage && (
                        <button
                            type="button"
                            onClick={() => setAdding(true)}
                            className="btn gap-2 bg-gray-900 text-gray-100 hover:bg-gray-800 dark:bg-gray-100 dark:text-gray-800 dark:hover:bg-white"
                        >
                            <UserPlus className="size-4" aria-hidden />
                            Add users
                        </button>
                    )
                }
            />

            <SectionGroup
                title={`Attached (${users.attached.total.toLocaleString()})`}
            >
                {(users.attached.total > 0 || isSearching) && (
                    <AttachedSearch value={term} onChange={setTerm} />
                )}
                {users.attached.total === 0 && isSearching ? (
                    <p className="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                        No attached users match "{search}".
                    </p>
                ) : users.attached.total === 0 ? (
                    <EmptyAttached />
                ) : (
                    <ul className="divide-y divide-gray-100 dark:divide-gray-700/60">
                        {users.attached.data.map((member) => (
                            <MemberRow
                                key={member.uuid}
                                member={member}
                                onRemove={
                                    canManage
                                        ? () => setRemoving(member)
                                        : undefined
                                }
                            />
                        ))}
                    </ul>
                )}
                {users.attached.total > users.attached.per_page && (
                    <div className="mt-4">
                        <DataTablePagination
                            pageIndex={users.attached.current_page - 1}
                            pageSize={users.attached.per_page}
                            totalRows={users.attached.total}
                            onPageChange={changePage}
                        />
                    </div>
                )}
            </SectionGroup>

            {users.allLocations.length > 0 && (
                <SectionGroup title="Also has access">
                    <div className="rounded-lg bg-gray-50 dark:bg-gray-900/20">
                        <button
                            type="button"
                            onClick={() => setShowAllLocations((open) => !open)}
                            aria-expanded={showAllLocations}
                            className="flex w-full cursor-pointer items-center gap-3 px-4 py-3 text-left text-sm"
                        >
                            <Globe
                                className="size-4 shrink-0 text-gray-400"
                                aria-hidden
                            />
                            <span className="flex-1 text-gray-600 dark:text-gray-300">
                                {users.allLocations.length}{' '}
                                {users.allLocations.length === 1
                                    ? 'user sees'
                                    : 'users see'}{' '}
                                every location, so they see this one without
                                being added.
                            </span>
                            <ChevronDown
                                aria-hidden
                                className={`size-4 shrink-0 text-gray-400 transition-transform ${showAllLocations ? 'rotate-180' : ''}`}
                            />
                        </button>
                        {showAllLocations && (
                            <ul className="divide-y divide-gray-100 border-t border-gray-100 px-4 dark:divide-gray-700/60 dark:border-gray-700/60">
                                {users.allLocations.map((member) => (
                                    <MemberRow
                                        key={member.uuid}
                                        member={member}
                                    />
                                ))}
                            </ul>
                        )}
                    </div>
                </SectionGroup>
            )}

            {canManage && (
                <>
                    <AttachUsersDialog
                        open={adding}
                        onOpenChange={setAdding}
                        locationUuid={location.uuid}
                        locationName={location.name}
                        candidates={attachableUsers}
                    />
                    <StatusConfirmDialog
                        open={removing !== null}
                        onOpenChange={(open) => !open && setRemoving(null)}
                        title={`Remove ${removing?.name ?? 'user'}?`}
                        description={`${removing?.name ?? 'They'} will no longer see ${location.name}'s check-ins and appointments. You can add them back at any time.`}
                        keepLabel="Keep"
                        confirmLabel={busy ? 'Removing…' : 'Remove'}
                        busy={busy}
                        onConfirm={remove}
                    />
                </>
            )}
        </>
    );
}

LocationUsersPage.layout = [DashboardLayout, LocationLayout];
