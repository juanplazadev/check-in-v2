import { Check, Search } from 'lucide-react';
import { useMemo, useState } from 'react';

import RoleChips from '@/components/dashboard/RoleChips';
import type { LocationStaffMember } from '@/components/dashboard/types';
import { Input } from '@/components/ui/input';
import { initialsOf } from '@/lib/dashboard/format';

/**
 * A searchable checkbox list of staff, for adding them to a location. Used by the Users section's
 * dialog and by the add location wizard.
 */
export default function StaffPicker({
    candidates,
    selected,
    onToggle,
    emptyMessage,
    autoFocus = true,
}: {
    /** Undefined while loading; shows a skeleton. */
    candidates: LocationStaffMember[] | undefined;
    selected: string[];
    onToggle: (uuid: string) => void;
    /** Shown when there is nobody to pick at all, as opposed to nobody matching the search. */
    emptyMessage: string;
    autoFocus?: boolean;
}) {
    const [search, setSearch] = useState('');

    const filtered = useMemo(() => {
        const term = search.trim().toLowerCase();

        return (candidates ?? []).filter(
            (member) =>
                term === '' ||
                member.name.toLowerCase().includes(term) ||
                member.email.toLowerCase().includes(term),
        );
    }, [candidates, search]);

    return (
        <>
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
                    autoFocus={autoFocus}
                />
            </div>

            <div className="max-h-80 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700/60">
                {candidates === undefined ? (
                    <div role="status" className="space-y-2 p-3">
                        <span className="sr-only">Loading users…</span>
                        <div className="h-12 animate-pulse rounded-lg bg-gray-100 dark:bg-gray-700/40" />
                        <div className="h-12 animate-pulse rounded-lg bg-gray-100 dark:bg-gray-700/40" />
                        <div className="h-12 animate-pulse rounded-lg bg-gray-100 dark:bg-gray-700/40" />
                    </div>
                ) : filtered.length === 0 ? (
                    <p className="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                        {candidates.length === 0
                            ? emptyMessage
                            : 'No users match your search.'}
                    </p>
                ) : (
                    <ul className="divide-y divide-gray-100 dark:divide-gray-700/60">
                        {filtered.map((member) => {
                            const isSelected = selected.includes(member.uuid);

                            return (
                                <li key={member.uuid}>
                                    <button
                                        type="button"
                                        role="checkbox"
                                        aria-checked={isSelected}
                                        onClick={() => onToggle(member.uuid)}
                                        className={`flex w-full cursor-pointer items-center gap-3 px-3 py-2.5 text-left ${
                                            isSelected
                                                ? 'bg-brand-green/[0.06] dark:bg-brand-green/[0.12]'
                                                : 'hover:bg-gray-50 dark:hover:bg-gray-700/30'
                                        }`}
                                    >
                                        <span
                                            className={`flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold ${
                                                isSelected
                                                    ? 'bg-brand-green text-white'
                                                    : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'
                                            }`}
                                        >
                                            {isSelected ? (
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
        </>
    );
}
