import type { ReactNode } from 'react';

interface CardProps {
    title?: ReactNode;
    /** Right side of the card header (a link, a legend, a menu). */
    action?: ReactNode;
    /** Grid placement inside the page's 12-column grid. */
    className?: string;
    children: ReactNode;
}

/** The white rounded card every Mosaic dashboard widget sits in. */
export default function Card({
    title,
    action,
    className = '',
    children,
}: CardProps) {
    return (
        <div
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
