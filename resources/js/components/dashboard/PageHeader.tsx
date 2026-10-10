import type { ReactNode } from 'react';

interface PageHeaderProps {
    title: string;
    subtitle?: ReactNode;
    /** Rendered on the right on `sm` and up (filters, date pickers, primary action). */
    actions?: ReactNode;
}

/** The title + actions row at the top of every Mosaic page. */
export default function PageHeader({
    title,
    subtitle,
    actions,
}: PageHeaderProps) {
    return (
        <div className="mb-8 sm:flex sm:items-center sm:justify-between">
            <div className="mb-4 sm:mb-0">
                <h1 className="text-2xl font-bold text-gray-800 md:text-3xl dark:text-gray-100">
                    {title}
                </h1>
                {subtitle && (
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {subtitle}
                    </p>
                )}
            </div>

            {actions && (
                <div className="grid grid-flow-col justify-start gap-2 sm:auto-cols-max sm:justify-end">
                    {actions}
                </div>
            )}
        </div>
    );
}
