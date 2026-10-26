import { ChevronRight, Lock, SearchX } from 'lucide-react';
import { useState } from 'react';

import GrantCell from '@/components/dashboard/roles/GrantCell';
import RoleLabel from '@/components/dashboard/roles/RoleLabel';
import { roleDisplayName } from '@/components/dashboard/roles/types';
import type {
    PermissionDefinition,
    PermissionGroupDefinition,
    RoleGrants,
} from '@/components/dashboard/roles/types';
import type { Permission } from '@/types/auth';

/**
 * The permissions down the side, grouped into collapsible sections, and one column per role.
 * A section's header row shows how much of it each role holds and, on an editable role, grants or
 * revokes the whole section in one click. Searching opens every section with a match.
 */
export default function PermissionMatrix({
    groups,
    permissions,
    roles,
    pendingFor,
    assignable,
    canEdit,
    search,
    onToggle,
    onSetGroup,
}: {
    groups: PermissionGroupDefinition[];
    permissions: PermissionDefinition[];
    roles: RoleGrants[];
    /** What the role will grant once the pending changes are saved. */
    pendingFor: (role: RoleGrants) => Set<Permission>;
    assignable: Set<Permission>;
    canEdit: boolean;
    search: string;
    onToggle: (role: RoleGrants, permission: Permission) => void;
    onSetGroup: (
        role: RoleGrants,
        permissions: Permission[],
        granted: boolean,
    ) => void;
}) {
    const [collapsed, setCollapsed] = useState<Set<string>>(new Set());

    const query = search.trim().toLowerCase();
    const visible = permissions.filter(
        (permission) =>
            query === '' ||
            permission.label.toLowerCase().includes(query) ||
            permission.description.toLowerCase().includes(query),
    );

    const sections = groups
        .map((group) => ({
            group,
            rows: visible.filter(
                (permission) => permission.group === group.key,
            ),
        }))
        .filter((section) => section.rows.length > 0);

    const pending = new Map(roles.map((role) => [role.name, pendingFor(role)]));
    const held = new Map(
        roles.map((role) => [role.name, new Set(role.permissions)]),
    );

    const toggleSection = (key: string) =>
        setCollapsed((current) => {
            const next = new Set(current);

            if (next.has(key)) {
                next.delete(key);
            } else {
                next.add(key);
            }

            return next;
        });

    if (sections.length === 0) {
        return (
            <div className="flex flex-col items-center gap-2 px-5 py-16 text-center">
                <SearchX className="size-8 text-gray-300 dark:text-gray-600" />
                <p className="font-medium text-gray-700 dark:text-gray-200">
                    No permissions match “{search.trim()}”
                </p>
                <p className="text-sm text-gray-500 dark:text-gray-400">
                    Try a shorter or different word.
                </p>
            </div>
        );
    }

    return (
        <div className="overflow-x-auto lg:overflow-visible">
            <table className="w-full table-fixed border-separate border-spacing-0 text-sm dark:text-gray-300">
                <colgroup>
                    <col className="w-72 lg:w-auto" />
                    {roles.map((role) => (
                        <col key={role.name} className="w-36" />
                    ))}
                </colgroup>

                <thead className="lg:sticky lg:top-16 lg:z-10">
                    <tr>
                        <th className="border-b border-gray-100 bg-white/95 px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase backdrop-blur dark:border-gray-700/60 dark:bg-gray-800/95 dark:text-gray-400">
                            Permission
                        </th>
                        {roles.map((role) => {
                            const count = pending.get(role.name)?.size ?? 0;

                            return (
                                <th
                                    key={role.name}
                                    className="border-b border-gray-100 bg-white/95 px-2 py-3 text-center font-normal backdrop-blur dark:border-gray-700/60 dark:bg-gray-800/95"
                                >
                                    <div className="flex items-center justify-center gap-1 whitespace-nowrap">
                                        <RoleLabel name={role.name} />
                                        {!role.editable && (
                                            <span
                                                title={
                                                    role.name === 'admin'
                                                        ? 'Fixed: admins always hold every permission.'
                                                        : 'Fixed: drivers only use the driver account.'
                                                }
                                            >
                                                <Lock
                                                    className="size-3 text-gray-400 dark:text-gray-500"
                                                    aria-label="Fixed role"
                                                />
                                            </span>
                                        )}
                                    </div>
                                    <div className="mt-1.5 text-xs text-gray-500 tabular-nums dark:text-gray-400">
                                        {count} of {permissions.length}
                                    </div>
                                    <div className="text-[11px] text-gray-400 tabular-nums dark:text-gray-500">
                                        {role.userCount}{' '}
                                        {role.userCount === 1
                                            ? 'user'
                                            : 'users'}
                                    </div>
                                </th>
                            );
                        })}
                    </tr>
                </thead>

                {sections.map(({ group, rows }) => {
                    const open = query !== '' || !collapsed.has(group.key);
                    const groupPermissions = permissions
                        .filter((permission) => permission.group === group.key)
                        .map((permission) => permission.name);

                    return (
                        <tbody key={group.key}>
                            <tr className="bg-gray-50/80 dark:bg-gray-900/30">
                                <th
                                    scope="rowgroup"
                                    className="border-b border-gray-100 px-3 py-2 text-left dark:border-gray-700/60"
                                >
                                    <button
                                        type="button"
                                        onClick={() => toggleSection(group.key)}
                                        aria-expanded={open}
                                        disabled={query !== ''}
                                        className="inline-flex cursor-pointer items-center gap-1.5 rounded-md px-2 py-1 text-xs font-semibold tracking-wide text-gray-600 uppercase hover:bg-gray-100 disabled:cursor-default disabled:hover:bg-transparent dark:text-gray-300 dark:hover:bg-gray-700/60"
                                    >
                                        <ChevronRight
                                            className={`size-3.5 transition-transform ${open ? 'rotate-90' : ''}`}
                                        />
                                        {group.label}
                                        <span className="font-normal text-gray-400 normal-case dark:text-gray-500">
                                            {groupPermissions.length}
                                        </span>
                                    </button>
                                </th>
                                {roles.map((role) => (
                                    <td
                                        key={role.name}
                                        className="border-b border-gray-100 px-2 py-2 text-center dark:border-gray-700/60"
                                    >
                                        <GroupPill
                                            role={role}
                                            groupLabel={group.label}
                                            permissions={groupPermissions.filter(
                                                (permission) =>
                                                    !role.editable ||
                                                    assignable.has(permission),
                                            )}
                                            pending={pending.get(role.name)!}
                                            interactive={
                                                canEdit && role.editable
                                            }
                                            onSet={(granted) =>
                                                onSetGroup(
                                                    role,
                                                    groupPermissions.filter(
                                                        (permission) =>
                                                            assignable.has(
                                                                permission,
                                                            ),
                                                    ),
                                                    granted,
                                                )
                                            }
                                        />
                                    </td>
                                ))}
                            </tr>

                            {open &&
                                rows.map((permission) => (
                                    <tr
                                        key={permission.name}
                                        className="group/row hover:bg-gray-50/60 dark:hover:bg-gray-900/20"
                                    >
                                        <td className="border-b border-gray-100 py-2.5 pr-3 pl-11 dark:border-gray-700/60">
                                            <div className="font-medium text-gray-800 dark:text-gray-100">
                                                {permission.label}
                                            </div>
                                            <div className="text-xs text-gray-500 dark:text-gray-400">
                                                {permission.description}
                                            </div>
                                        </td>
                                        {roles.map((role) => (
                                            <td
                                                key={role.name}
                                                className="border-b border-gray-100 px-2 py-1.5 text-center dark:border-gray-700/60"
                                            >
                                                <GrantCell
                                                    held={held
                                                        .get(role.name)!
                                                        .has(permission.name)}
                                                    pending={pending
                                                        .get(role.name)!
                                                        .has(permission.name)}
                                                    editable={
                                                        canEdit && role.editable
                                                    }
                                                    assignable={
                                                        !role.editable ||
                                                        assignable.has(
                                                            permission.name,
                                                        )
                                                    }
                                                    permissionLabel={
                                                        permission.label
                                                    }
                                                    roleLabel={roleDisplayName(
                                                        role.name,
                                                    )}
                                                    onToggle={() =>
                                                        onToggle(
                                                            role,
                                                            permission.name,
                                                        )
                                                    }
                                                />
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                        </tbody>
                    );
                })}
            </table>
        </div>
    );
}

/**
 * How much of a section a role holds: All, None, or a count. On an editable role it grants the
 * rest of the section, or revokes all of it once everything is held.
 */
function GroupPill({
    role,
    groupLabel,
    permissions,
    pending,
    interactive,
    onSet,
}: {
    role: RoleGrants;
    groupLabel: string;
    permissions: Permission[];
    pending: Set<Permission>;
    interactive: boolean;
    onSet: (granted: boolean) => void;
}) {
    if (permissions.length === 0) {
        return (
            <span className="text-xs text-gray-300 dark:text-gray-600">–</span>
        );
    }

    const heldCount = permissions.filter((permission) =>
        pending.has(permission),
    ).length;
    const all = heldCount === permissions.length;

    const tone = all
        ? 'bg-brand-green/12 text-brand-green'
        : heldCount > 0
          ? 'bg-amber-500/12 text-amber-700 dark:text-amber-300'
          : 'bg-gray-100 text-gray-500 dark:bg-gray-700/60 dark:text-gray-400';

    const content = all
        ? 'All'
        : heldCount === 0
          ? 'None'
          : `${heldCount}/${permissions.length}`;

    const base = `inline-flex min-w-12 items-center justify-center rounded-full px-2 py-0.5 text-xs font-semibold tabular-nums ${tone}`;

    if (!interactive) {
        return (
            <span className={`${base} ${role.editable ? '' : 'opacity-60'}`}>
                {content}
            </span>
        );
    }

    const roleName = roleDisplayName(role.name);

    return (
        <button
            type="button"
            onClick={() => onSet(!all)}
            title={
                all
                    ? `Revoke every ${groupLabel} permission from ${roleName}`
                    : `Grant every ${groupLabel} permission to ${roleName}`
            }
            className={`${base} cursor-pointer ring-current/20 transition hover:ring-2 focus-visible:ring-2 focus-visible:ring-violet-500 focus-visible:outline-none`}
        >
            {content}
        </button>
    );
}
