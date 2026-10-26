import type { SentryLevel } from '@/components/dashboard/errors/types';

export const LEVEL_CLASSES: Record<SentryLevel, string> = {
    fatal: 'bg-red-600',
    error: 'bg-rose-500',
    warning: 'bg-amber-400',
    info: 'bg-sky-400',
    debug: 'bg-gray-400',
};

/** A Sentry level as a colored dot, or a dot and its name. */
export default function LevelBadge({
    level,
    withLabel = false,
}: {
    level: SentryLevel;
    withLabel?: boolean;
}) {
    const dot = (
        <span
            className={`inline-block size-2.5 shrink-0 rounded-full ${LEVEL_CLASSES[level] ?? LEVEL_CLASSES.error}`}
            aria-hidden={withLabel}
            title={withLabel ? undefined : level}
        />
    );

    if (!withLabel) {
        return dot;
    }

    return (
        <span className="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600 capitalize dark:bg-gray-700/60 dark:text-gray-300">
            {dot}
            {level}
        </span>
    );
}
