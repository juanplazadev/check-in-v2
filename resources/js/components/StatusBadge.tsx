interface StatusStyle {
    className: string;
    dot: string;
}

// Check-ins use pending/completed/cancelled; appointments add the other three.
const STATUS_STYLES: Record<string, StatusStyle> = {
    pending: {
        className: 'bg-amber-500/12 text-amber-700 dark:text-amber-400',
        dot: 'bg-amber-500',
    },
    completed: {
        className: 'bg-brand-green/12 text-brand-green',
        dot: 'bg-brand-green',
    },
    cancelled: {
        className: 'bg-gray-400/15 text-gray-500 dark:text-gray-400',
        dot: 'bg-gray-400',
    },
    // A soft-deleted check-in, shown beside its real status.
    archived: {
        className: 'bg-gray-400/15 text-gray-500 dark:text-gray-400',
        dot: 'bg-gray-400',
    },
    scheduled: {
        className: 'bg-blue-500/12 text-blue-700 dark:text-blue-400',
        dot: 'bg-blue-500',
    },
    'no-show': {
        className: 'bg-rose-500/12 text-rose-700 dark:text-rose-400',
        dot: 'bg-rose-500',
    },
    // Ringed so it reads as distinct from completed at a glance.
    'checked-in': {
        className:
            'bg-brand-green/12 text-brand-green ring-1 ring-brand-green/30',
        dot: 'bg-brand-green',
    },
    // ERP sync states (CheckInErpStatus) shown on the staff dashboard; `pending` is shared above.
    synced: {
        className: 'bg-brand-green/12 text-brand-green',
        dot: 'bg-brand-green',
    },
    failed: {
        className: 'bg-rose-500/12 text-rose-700 dark:text-rose-400',
        dot: 'bg-rose-500',
    },
};

export default function StatusBadge({
    status,
    label,
}: {
    status: string;
    label: string;
}) {
    const badge = STATUS_STYLES[status] ?? STATUS_STYLES.pending;

    return (
        <span
            className={`inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold ${badge.className}`}
        >
            <span className={`h-1.5 w-1.5 rounded-full ${badge.dot}`} />
            {label}
        </span>
    );
}
