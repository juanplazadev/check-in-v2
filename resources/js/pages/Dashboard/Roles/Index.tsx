import { Head, router, useForm } from '@inertiajs/react';
import { Check, Lock, Minus, Plus, Search } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

import { update } from '@/actions/App/Http/Controllers/Dashboard/RolePermissionController';
import Card from '@/components/dashboard/Card';
import PageHeader from '@/components/dashboard/PageHeader';
import RequirePermission from '@/components/dashboard/RequirePermission';
import PermissionMatrix from '@/components/dashboard/roles/PermissionMatrix';
import ReviewChangesDialog from '@/components/dashboard/roles/ReviewChangesDialog';
import SaveBar from '@/components/dashboard/roles/SaveBar';
import type {
    DraftGrants,
    PermissionDefinition,
    PermissionGroupDefinition,
    RoleChange,
    RoleGrants,
} from '@/components/dashboard/roles/types';
import { useCan } from '@/hooks/useCan';
import DashboardLayout from '@/layouts/DashboardLayout';
import type { Permission } from '@/types/auth';

interface RolesIndexProps {
    groups: PermissionGroupDefinition[];
    permissions: PermissionDefinition[];
    roles: RoleGrants[];
    /** What an editable role may hold: everything but the driver account. */
    assignable: Permission[];
}

function draftFrom(roles: RoleGrants[]): DraftGrants {
    return Object.fromEntries(
        roles
            .filter((role) => role.editable)
            .map((role) => [role.name, role.permissions]),
    );
}

export default function RolesIndex({
    groups,
    permissions,
    roles,
    assignable,
}: RolesIndexProps) {
    const can = useCan();
    const canEdit = can('edit roles and permissions');

    const [search, setSearch] = useState('');
    const [reviewing, setReviewing] = useState(false);

    const form = useForm<{ roles: DraftGrants }>({ roles: draftFrom(roles) });

    const assignableSet = useMemo(() => new Set(assignable), [assignable]);

    const changes = useMemo<RoleChange[]>(
        () =>
            roles
                .filter((role) => role.editable)
                .map((role) => {
                    const before = new Set(role.permissions);
                    const after = new Set(form.data.roles[role.name] ?? []);

                    return {
                        role,
                        granted: permissions.filter(
                            (permission) =>
                                after.has(permission.name) &&
                                !before.has(permission.name),
                        ),
                        revoked: permissions.filter(
                            (permission) =>
                                before.has(permission.name) &&
                                !after.has(permission.name),
                        ),
                    };
                })
                .filter(
                    (change) =>
                        change.granted.length > 0 || change.revoked.length > 0,
                ),
        [roles, permissions, form.data.roles],
    );

    const hasChanges = changes.length > 0;

    const pendingFor = (role: RoleGrants): Set<Permission> =>
        new Set(
            role.editable
                ? (form.data.roles[role.name] ?? [])
                : role.permissions,
        );

    const setRole = (role: RoleGrants, next: Set<Permission>) => {
        form.clearErrors();
        form.setData('roles', {
            ...form.data.roles,
            // Kept in matrix order, so the payload reads like the page.
            [role.name]: permissions
                .map((permission) => permission.name)
                .filter((name) => next.has(name)),
        });
    };

    const toggle = (role: RoleGrants, permission: Permission) => {
        const next = pendingFor(role);

        if (next.has(permission)) {
            next.delete(permission);
        } else {
            next.add(permission);
        }

        setRole(role, next);
    };

    const setGroup = (
        role: RoleGrants,
        groupPermissions: Permission[],
        granted: boolean,
    ) => {
        const next = pendingFor(role);

        for (const permission of groupPermissions) {
            if (granted) {
                next.add(permission);
            } else {
                next.delete(permission);
            }
        }

        setRole(role, next);
    };

    const discard = () => {
        form.reset();
        form.clearErrors();
    };

    const save = () => {
        if (form.processing) {
            return;
        }

        // Only the roles that changed; the rest are left exactly as they are.
        form.transform((data) => ({
            roles: Object.fromEntries(
                changes.map((change) => [
                    change.role.name,
                    data.roles[change.role.name] ?? [],
                ]),
            ),
        }));

        form.submit(update(), {
            preserveScroll: true,
            onSuccess: () => {
                form.setDefaults();
                setReviewing(false);
            },
        });
    };

    // Read by the listeners below, which are bound once.
    const latest = useRef({ hasChanges });

    useEffect(() => {
        latest.current = { hasChanges };
    });

    useEffect(() => {
        const onBeforeUnload = (event: BeforeUnloadEvent) => {
            if (latest.current.hasChanges) {
                event.preventDefault();
            }
        };

        // Saving is a PATCH; only navigating away to another page asks.
        const removeBefore = router.on('before', (event) => {
            if (
                latest.current.hasChanges &&
                event.detail.visit.method === 'get' &&
                !window.confirm('Leave without saving your permission changes?')
            ) {
                event.preventDefault();
            }
        });

        window.addEventListener('beforeunload', onBeforeUnload);

        return () => {
            window.removeEventListener('beforeunload', onBeforeUnload);
            removeBefore();
        };
    }, []);

    const error = Object.values(form.errors)[0] ?? null;

    return (
        <RequirePermission permission="view roles and permissions">
            <Head title="Roles & permissions" />

            <PageHeader
                title="Roles & permissions"
                subtitle={
                    canEdit
                        ? 'Choose which permissions each role grants. Admin and Driver are fixed.'
                        : 'Which permissions each role grants.'
                }
            />

            <Card
                title={`${permissions.length} permissions`}
                action={
                    <div className="relative">
                        <label htmlFor="permission-search" className="sr-only">
                            Search permissions
                        </label>
                        <input
                            id="permission-search"
                            className="form-input w-44 bg-white pl-9 sm:w-64 dark:bg-gray-800"
                            type="search"
                            placeholder="Search permissions…"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                        />
                        <div className="pointer-events-none absolute inset-0 right-auto flex items-center">
                            <Search className="mr-2 ml-3 size-4 shrink-0 text-gray-400 dark:text-gray-500" />
                        </div>
                    </div>
                }
            >
                {canEdit && <Legend />}

                <PermissionMatrix
                    groups={groups}
                    permissions={permissions}
                    roles={roles}
                    pendingFor={pendingFor}
                    assignable={assignableSet}
                    canEdit={canEdit}
                    search={search}
                    onToggle={toggle}
                    onSetGroup={setGroup}
                />
            </Card>

            {canEdit && hasChanges && (
                <SaveBar
                    changes={changes}
                    busy={form.processing}
                    onDiscard={discard}
                    onReview={() => setReviewing(true)}
                />
            )}

            <ReviewChangesDialog
                open={reviewing && hasChanges}
                onOpenChange={setReviewing}
                changes={changes}
                busy={form.processing}
                error={error}
                onConfirm={save}
            />
        </RequirePermission>
    );
}

/** What the cell marks mean, above the matrix. */
function Legend() {
    return (
        <div className="flex flex-wrap items-center gap-x-5 gap-y-2 border-b border-gray-100 px-5 py-3 text-xs text-gray-500 dark:border-gray-700/60 dark:text-gray-400">
            <span className="inline-flex items-center gap-1.5">
                <span className="flex size-5 items-center justify-center rounded-md bg-brand-green/12 text-brand-green">
                    <Check className="size-3" strokeWidth={2.5} />
                </span>
                Granted
            </span>
            <span className="inline-flex items-center gap-1.5">
                <span className="flex size-5 items-center justify-center rounded-md bg-emerald-500 text-white">
                    <Plus className="size-3" strokeWidth={3} />
                </span>
                Will be granted
            </span>
            <span className="inline-flex items-center gap-1.5">
                <span className="flex size-5 items-center justify-center rounded-md bg-rose-500/10 text-rose-600 ring-1 ring-rose-500/40 ring-inset dark:text-rose-400">
                    <Minus className="size-3" strokeWidth={3} />
                </span>
                Will be revoked
            </span>
            <span className="inline-flex items-center gap-1.5">
                <Lock className="size-3.5" />
                Fixed role
            </span>
            <span className="text-gray-400 dark:text-gray-500">
                Click a cell to toggle it, or a section’s pill to toggle the
                whole section.
            </span>
        </div>
    );
}

RolesIndex.layout = DashboardLayout;
