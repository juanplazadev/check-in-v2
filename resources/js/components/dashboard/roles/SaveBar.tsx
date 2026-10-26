import { roleDisplayName } from '@/components/dashboard/roles/types';
import type { RoleChange } from '@/components/dashboard/roles/types';

/**
 * Floats at the bottom of the page while there are unsaved changes, counting them per role, with
 * Discard and the way into the review dialog.
 */
export default function SaveBar({
    changes,
    busy,
    onDiscard,
    onReview,
}: {
    changes: RoleChange[];
    busy: boolean;
    onDiscard: () => void;
    onReview: () => void;
}) {
    const total = changes.reduce(
        (sum, change) => sum + change.granted.length + change.revoked.length,
        0,
    );

    return (
        <div className="sticky bottom-4 z-20 mt-6 animate-in duration-200 fade-in-0 slide-in-from-bottom-4">
            <div
                role="status"
                className="mx-auto flex max-w-3xl flex-col gap-3 rounded-2xl bg-gray-900 px-4 py-3 text-gray-100 shadow-2xl ring-1 ring-black/5 sm:flex-row sm:items-center sm:justify-between sm:pl-5 dark:bg-gray-100 dark:text-gray-800"
            >
                <div className="min-w-0">
                    <p className="font-semibold">
                        {total} unsaved {total === 1 ? 'change' : 'changes'}
                    </p>
                    <p className="truncate text-xs text-gray-400 dark:text-gray-500">
                        {changes
                            .map(
                                (change) =>
                                    `${roleDisplayName(change.role.name)}: ${[
                                        change.granted.length > 0 &&
                                            `+${change.granted.length}`,
                                        change.revoked.length > 0 &&
                                            `−${change.revoked.length}`,
                                    ]
                                        .filter(Boolean)
                                        .join(' ')}`,
                            )
                            .join(' · ')}
                    </p>
                </div>

                <div className="flex shrink-0 gap-2">
                    <button
                        type="button"
                        onClick={onDiscard}
                        disabled={busy}
                        className="btn-sm text-gray-300 hover:bg-white/10 hover:text-white disabled:opacity-50 dark:text-gray-600 dark:hover:bg-gray-900/10 dark:hover:text-gray-900"
                    >
                        Discard
                    </button>
                    <button
                        type="button"
                        onClick={onReview}
                        disabled={busy}
                        className="btn-sm bg-white text-gray-900 hover:bg-gray-100 disabled:opacity-50 dark:bg-gray-900 dark:text-gray-100 dark:hover:bg-gray-800"
                    >
                        Review & save
                    </button>
                </div>
            </div>
        </div>
    );
}
