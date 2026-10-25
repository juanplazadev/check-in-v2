import type { ReactNode } from 'react';

/** The title row of a location section, inside LocationLayout's panel. */
export default function SectionHeader({
    title,
    description,
    action,
}: {
    title: string;
    description?: ReactNode;
    /** Right side on `sm` and up, such as a primary button. */
    action?: ReactNode;
}) {
    return (
        <div className="mb-6 sm:flex sm:items-start sm:justify-between sm:gap-4">
            <div className="min-w-0">
                <h2 className="text-xl font-bold text-gray-800 dark:text-gray-100">
                    {title}
                </h2>
                {description && (
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {description}
                    </p>
                )}
            </div>
            {action && <div className="mt-3 shrink-0 sm:mt-0">{action}</div>}
        </div>
    );
}

/** A titled group of rows within a section, such as "Booking rules". */
export function SectionGroup({
    title,
    action,
    children,
}: {
    title: string;
    /** Right of the title, such as an Edit button. */
    action?: ReactNode;
    children: ReactNode;
}) {
    return (
        <section className="mb-8 last:mb-0">
            <div className="mb-3 flex min-h-5 items-center justify-between gap-2">
                <h3 className="text-xs font-semibold text-gray-400 uppercase dark:text-gray-500">
                    {title}
                </h3>
                {action}
            </div>
            {children}
        </section>
    );
}
