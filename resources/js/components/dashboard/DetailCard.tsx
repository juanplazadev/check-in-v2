import type { ReactNode } from 'react';

import Card from '@/components/dashboard/Card';

/** A titled card of label/value rows, for record detail pages. */
export function DetailCard({
    title,
    className = '',
    children,
}: {
    title: string;
    className?: string;
    children: ReactNode;
}) {
    return (
        <Card title={title} className={className}>
            <dl className="divide-y divide-gray-100 px-5 text-sm dark:divide-gray-700/60">
                {children}
            </dl>
        </Card>
    );
}

export function DetailRow({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-1 py-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
            <dt className="shrink-0 text-gray-500 dark:text-gray-400">
                {label}
            </dt>
            <dd className="font-medium text-gray-800 sm:text-right dark:text-gray-100">
                {children ?? (
                    <span className="text-gray-400 dark:text-gray-500">-</span>
                )}
            </dd>
        </div>
    );
}
