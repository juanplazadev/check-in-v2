import { ArrowRight, History } from 'lucide-react';

import Card from '@/components/dashboard/Card';
import type {
    RecordHistoryChange,
    RecordHistoryEntry,
} from '@/components/dashboard/types';
import { formatDateTime } from '@/lib/dashboard/format';

const DOT_CLASSES: Partial<Record<RecordHistoryEntry['event'], string>> = {
    'notification-failed': 'bg-red-500',
    'notification-skipped': 'bg-amber-500',
    'status-changed': 'bg-sky-500',
    'details-edited': 'bg-violet-500',
    reinstated: 'bg-emerald-500',
    archived: 'bg-gray-500',
    restored: 'bg-emerald-500',
    'appointment-linked': 'bg-blue-500',
    'appointment-unlinked': 'bg-gray-500',
    'location-user-attached': 'bg-blue-500',
    'location-user-detached': 'bg-gray-500',
    'check-ins-paused': 'bg-amber-500',
    'check-ins-resumed': 'bg-emerald-500',
    'appointments-paused': 'bg-amber-500',
    'appointments-resumed': 'bg-emerald-500',
    'location-deleted': 'bg-red-500',
    'location-restored': 'bg-emerald-500',
    'check-in-hours-edited': 'bg-violet-500',
    'appointment-hours-edited': 'bg-violet-500',
    'check-in-options-edited': 'bg-violet-500',
    'appointment-options-edited': 'bg-violet-500',
    'closure-added': 'bg-rose-500',
    'closure-removed': 'bg-gray-500',
};

function ChangeValue({ value }: { value: string | null }) {
    return value === null ? (
        <span className="text-gray-400 italic dark:text-gray-500">empty</span>
    ) : (
        <span className="break-words">{value}</span>
    );
}

/** A staff edit's fields, each as label, old value struck through, new value. */
function ChangeList({ changes }: { changes: RecordHistoryChange[] }) {
    return (
        <dl className="mt-1.5 space-y-1 rounded-lg bg-gray-50 px-3 py-2 text-xs dark:bg-gray-900/40">
            {changes.map((change) => (
                <div key={change.field} className="flex flex-wrap gap-x-2">
                    <dt className="font-medium text-gray-500 dark:text-gray-400">
                        {change.label}
                    </dt>
                    <dd className="flex min-w-0 flex-wrap items-center gap-1.5 text-gray-700 dark:text-gray-200">
                        {change.redacted ? (
                            <span className="text-gray-400 italic dark:text-gray-500">
                                changed
                            </span>
                        ) : (
                            <>
                                <span className="text-gray-400 line-through dark:text-gray-500">
                                    <ChangeValue value={change.from} />
                                </span>
                                <ArrowRight
                                    className="size-3 shrink-0 text-gray-400"
                                    aria-label="changed to"
                                />
                                <ChangeValue value={change.to} />
                            </>
                        )}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

/** History entries, in the order given, as a vertical timeline without a card around it. */
export function HistoryEntries({
    entries,
    className = '',
}: {
    entries: RecordHistoryEntry[];
    className?: string;
}) {
    if (entries.length === 0) {
        return (
            <div
                className={`flex flex-col items-center gap-2 px-5 py-8 text-center ${className}`}
            >
                <History className="size-6 text-gray-300 dark:text-gray-600" />
                <p className="text-sm text-gray-500 dark:text-gray-400">
                    No history yet.
                </p>
            </div>
        );
    }

    return (
        <ol className={className}>
            {entries.map((entry) => (
                <li key={entry.id} className="relative pb-5 pl-6 last:pb-0">
                    <span
                        className="absolute top-3 bottom-0 left-[3px] w-px bg-gray-200 dark:bg-gray-700 [li:last-child>&]:hidden"
                        aria-hidden="true"
                    />
                    <span
                        className={`absolute top-1.5 left-0 size-2 rounded-full ${DOT_CLASSES[entry.event] ?? 'bg-brand-green'}`}
                        aria-hidden="true"
                    />
                    <div className="text-sm font-medium text-gray-800 dark:text-gray-100">
                        {entry.description}
                    </div>
                    {entry.changes !== null && entry.changes.length > 0 && (
                        <ChangeList changes={entry.changes} />
                    )}
                    <div className="text-xs text-gray-400 dark:text-gray-500">
                        {formatDateTime(entry.createdAt)}
                        {entry.actor && ` · ${entry.actor}`}
                    </div>
                </li>
            ))}
        </ol>
    );
}

/** A record's RecordHistory, oldest first, as a vertical timeline in a card. */
export default function HistoryTimeline({
    entries,
    className = '',
}: {
    entries: RecordHistoryEntry[];
    className?: string;
}) {
    return (
        <Card title="History" className={className}>
            <HistoryEntries
                entries={entries}
                className={entries.length === 0 ? '' : 'px-5 py-4'}
            />
        </Card>
    );
}
