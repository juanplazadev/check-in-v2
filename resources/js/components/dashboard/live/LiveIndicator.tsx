import type { FeedStatus } from '@/hooks/useFeedConnection';

const STATES: Record<FeedStatus, { label: string; dot: string; text: string }> =
    {
        live: {
            label: 'Live',
            dot: 'bg-green-500',
            text: 'text-green-700 dark:text-green-400',
        },
        connecting: {
            label: 'Reconnecting…',
            dot: 'bg-amber-500',
            text: 'text-amber-700 dark:text-amber-400',
        },
        offline: {
            label: 'Offline',
            dot: 'bg-red-500',
            text: 'text-red-700 dark:text-red-400',
        },
    };

/** Whether the screen is still receiving check-ins, so nobody trusts a stale one. */
export default function LiveIndicator({ status }: { status: FeedStatus }) {
    const state = STATES[status];

    return (
        <span
            className={`inline-flex items-center gap-2 rounded-full bg-white px-3 py-1 text-sm font-medium shadow-sm dark:bg-gray-800 ${state.text}`}
            role="status"
        >
            <span className="relative flex size-2.5">
                {status === 'live' && (
                    <span
                        className={`absolute inline-flex size-full animate-ping rounded-full opacity-60 ${state.dot}`}
                    />
                )}
                <span
                    className={`relative inline-flex size-2.5 rounded-full ${state.dot}`}
                />
            </span>
            {state.label}
        </span>
    );
}
