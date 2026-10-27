import { STATUS_META } from '@/components/dashboard/messages/meta';
import type { MessageStatus } from '@/components/dashboard/messages/types';

/** A message's status as a dotted pill; the description shows on hover. */
export default function MessageStatusBadge({
    status,
    size = 'sm',
}: {
    status: MessageStatus;
    size?: 'sm' | 'md';
}) {
    const meta = STATUS_META[status];

    return (
        <span
            title={meta.description}
            className={`inline-flex items-center gap-1.5 rounded-full font-medium ${meta.badge} ${
                size === 'md' ? 'px-2.5 py-1 text-sm' : 'px-2 py-0.5 text-xs'
            }`}
        >
            <span className="relative flex size-1.5">
                {(status === 'queued' || status === 'sent') && (
                    <span
                        className={`absolute inline-flex size-full animate-ping rounded-full opacity-60 ${meta.dot}`}
                        aria-hidden
                    />
                )}
                <span
                    className={`relative inline-flex size-1.5 rounded-full ${meta.dot}`}
                    aria-hidden
                />
            </span>
            {meta.label}
        </span>
    );
}
