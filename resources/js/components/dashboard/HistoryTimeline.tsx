import Card from '@/components/dashboard/Card';
import type { RecordHistoryEntry } from '@/components/dashboard/types';
import { formatDateTime } from '@/lib/dashboard/format';

const DOT_CLASSES: Partial<Record<RecordHistoryEntry['event'], string>> = {
    'notification-failed': 'bg-red-500',
    'status-changed': 'bg-sky-500',
};

/** A record's RecordHistory, oldest first, as a vertical timeline. */
export default function HistoryTimeline({
    entries,
    className = '',
}: {
    entries: RecordHistoryEntry[];
    className?: string;
}) {
    return (
        <Card title="History" className={className}>
            <ol className="px-5 py-4">
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
                        <div className="text-xs text-gray-400 dark:text-gray-500">
                            {formatDateTime(entry.createdAt)}
                            {entry.actor && ` · ${entry.actor}`}
                        </div>
                    </li>
                ))}
            </ol>
        </Card>
    );
}
