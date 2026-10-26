import { MapPin, Minus, Plus } from 'lucide-react';

import { SECONDARY_BUTTON } from '@/components/dashboard/RecordActionDialogs';
import RoleLabel from '@/components/dashboard/roles/RoleLabel';
import type { RoleChange } from '@/components/dashboard/roles/types';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';

/**
 * Spells out what saving will do, role by role, before anything changes: who gains what, who
 * loses what, and how many people feel it on their next click.
 */
export default function ReviewChangesDialog({
    open,
    onOpenChange,
    changes,
    busy,
    error,
    onConfirm,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    changes: RoleChange[];
    busy: boolean;
    error: string | null;
    onConfirm: () => void;
}) {
    const affectedUsers = changes.reduce(
        (total, change) => total + change.role.userCount,
        0,
    );

    const movesLocationScope = changes.some((change) =>
        [...change.granted, ...change.revoked].some(
            (permission) => permission.name === 'view all locations',
        ),
    );

    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent className="dashboard sm:max-w-lg">
                <AlertDialogHeader>
                    <AlertDialogTitle>
                        Review permission changes
                    </AlertDialogTitle>
                    <AlertDialogDescription>
                        {affectedUsers === 0
                            ? 'No one holds these roles yet, so nobody is affected right away.'
                            : `Takes effect for ${affectedUsers} ${affectedUsers === 1 ? 'user' : 'users'} on their next page load.`}
                    </AlertDialogDescription>
                </AlertDialogHeader>

                <div className="-mx-1 max-h-[55vh] space-y-3 overflow-y-auto px-1">
                    {changes.map((change) => (
                        <section
                            key={change.role.name}
                            className="rounded-xl border border-gray-200 p-4 dark:border-gray-700/60"
                        >
                            <header className="mb-3 flex items-center justify-between gap-2">
                                <RoleLabel name={change.role.name} />
                                <span className="text-xs text-gray-500 dark:text-gray-400">
                                    {change.role.userCount}{' '}
                                    {change.role.userCount === 1
                                        ? 'user'
                                        : 'users'}
                                </span>
                            </header>

                            <ul className="space-y-1.5 text-sm">
                                {change.granted.map((permission) => (
                                    <li
                                        key={permission.name}
                                        className="flex items-center gap-2 text-gray-700 dark:text-gray-200"
                                    >
                                        <span className="flex size-5 shrink-0 items-center justify-center rounded-md bg-emerald-500 text-white">
                                            <Plus
                                                className="size-3"
                                                strokeWidth={3}
                                                aria-label="Grant"
                                            />
                                        </span>
                                        {permission.label}
                                    </li>
                                ))}
                                {change.revoked.map((permission) => (
                                    <li
                                        key={permission.name}
                                        className="flex items-center gap-2 text-gray-500 dark:text-gray-400"
                                    >
                                        <span className="flex size-5 shrink-0 items-center justify-center rounded-md bg-rose-500/10 text-rose-600 ring-1 ring-rose-500/40 ring-inset dark:text-rose-400">
                                            <Minus
                                                className="size-3"
                                                strokeWidth={3}
                                                aria-label="Revoke"
                                            />
                                        </span>
                                        <span className="line-through decoration-rose-400/60">
                                            {permission.label}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    ))}

                    {movesLocationScope && (
                        <p className="flex gap-2 rounded-xl bg-amber-500/10 p-3 text-sm text-amber-800 dark:text-amber-200">
                            <MapPin className="mt-0.5 size-4 shrink-0" />
                            “View all locations” changes which locations these
                            users see. Without it, they only see the locations
                            they are attached to.
                        </p>
                    )}
                </div>

                {error !== null && (
                    <p className="text-sm text-red-500">{error}</p>
                )}

                <AlertDialogFooter>
                    <AlertDialogCancel asChild>
                        <button
                            type="button"
                            disabled={busy}
                            className={SECONDARY_BUTTON}
                        >
                            Keep editing
                        </button>
                    </AlertDialogCancel>
                    <button
                        type="button"
                        disabled={busy}
                        onClick={onConfirm}
                        className="btn bg-gray-900 text-gray-100 hover:bg-gray-800 disabled:opacity-50 dark:bg-gray-100 dark:text-gray-800 dark:hover:bg-white"
                    >
                        {busy ? 'Saving…' : 'Save changes'}
                    </button>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
