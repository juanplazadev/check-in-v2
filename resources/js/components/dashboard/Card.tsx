import type { ReactNode } from 'react';

interface CardProps {
    /** Anchor target, e.g. for an in-page link to this card. */
    id?: string;
    title?: ReactNode;
    /** Right side of the card header (a link, a legend, a menu). */
    action?: ReactNode;
    /** Grid placement inside the page's 12-column grid. */
    className?: string;
    children: ReactNode;
}

/** The white rounded card every Mosaic dashboard widget sits in. */
export default function Card({
    id,
    title,
    action,
    className = '',
    children,
}: CardProps) {
    return (
        <div
            id={id}
            className={`flex flex-col rounded-xl bg-white shadow-xs dark:bg-gray-800 ${className}`}
        >
            {(title !== undefined || action !== undefined) && (
                <header className="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-700/60">
                    <h2 className="font-semibold text-gray-800 dark:text-gray-100">
                        {title}
                    </h2>
                    {action}
                </header>
            )}
            {children}
        </div>
    );
}
