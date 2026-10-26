import { CloudOff, PlugZap, SearchX } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

const STATES: Record<
    'unconfigured' | 'missing' | 'unavailable',
    { icon: LucideIcon; title: string; body: string }
> = {
    unconfigured: {
        icon: PlugZap,
        title: 'Sentry is not connected',
        body: 'Set SENTRY_AUTH_TOKEN, SENTRY_ORG and SENTRY_PROJECT in the environment to read errors and releases from Sentry.',
    },
    unavailable: {
        icon: CloudOff,
        title: 'Sentry could not be reached',
        body: 'The Sentry API did not answer or refused the request. Try again in a minute.',
    },
    missing: {
        icon: SearchX,
        title: 'Issue not found',
        body: 'Sentry no longer has this issue. It may have been deleted or merged into another.',
    },
};

/** The panel shown in place of Sentry data when there is none to show. */
export default function SentryState({
    state,
    className = '',
}: {
    state: keyof typeof STATES;
    className?: string;
}) {
    const { icon: Icon, title, body } = STATES[state];

    return (
        <div
            className={`flex flex-col items-center rounded-xl bg-white px-5 py-16 text-center shadow-xs dark:bg-gray-800 ${className}`}
        >
            <span className="mb-4 flex size-14 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700">
                <Icon className="size-6 text-gray-400" />
            </span>
            <h2 className="mb-2 text-lg font-semibold text-gray-800 dark:text-gray-100">
                {title}
            </h2>
            <p className="max-w-md text-sm text-gray-500 dark:text-gray-400">
                {body}
            </p>
        </div>
    );
}
