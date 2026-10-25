import { Mail } from 'lucide-react';
import type { ReactNode } from 'react';

/** A label and its current value, for the read-only options sections. */
export function OptionRow({
    label,
    description,
    children,
}: {
    label: string;
    description?: string;
    children: ReactNode;
}) {
    return (
        <div className="flex items-start justify-between gap-4 py-3">
            <div className="min-w-0">
                <div className="text-sm font-medium text-gray-800 dark:text-gray-100">
                    {label}
                </div>
                {description && (
                    <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        {description}
                    </p>
                )}
            </div>
            <div className="shrink-0 text-sm font-semibold text-gray-800 tabular-nums dark:text-gray-100">
                {children}
            </div>
        </div>
    );
}

export function OptionRows({ children }: { children: ReactNode }) {
    return (
        <div className="divide-y divide-gray-100 dark:divide-gray-700/60">
            {children}
        </div>
    );
}

/** Whether an optional form field is collected. */
export function ShownBadge({ shown }: { shown: boolean }) {
    return (
        <span
            className={`rounded-full px-2 py-0.5 text-xs font-semibold ${
                shown
                    ? 'bg-brand-green/12 text-brand-green'
                    : 'bg-gray-100 text-gray-500 dark:bg-gray-700/60 dark:text-gray-400'
            }`}
        >
            {shown ? 'Shown' : 'Hidden'}
        </span>
    );
}

/** The staff addresses a location emails, or a note that nobody is. */
export function NotificationEmails({
    emails,
    empty,
}: {
    emails: string[];
    empty: string;
}) {
    if (emails.length === 0) {
        return (
            <p className="rounded-lg border border-dashed border-gray-200 px-4 py-5 text-center text-sm text-gray-500 dark:border-gray-700/60 dark:text-gray-400">
                {empty}
            </p>
        );
    }

    return (
        <ul className="flex flex-wrap gap-2">
            {emails.map((email) => (
                <li
                    key={email}
                    className="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-3 py-1 text-sm text-gray-700 dark:bg-gray-700/60 dark:text-gray-200"
                >
                    <Mail className="size-3.5 text-gray-400" aria-hidden />
                    {email}
                </li>
            ))}
        </ul>
    );
}
