import type { LucideIcon } from 'lucide-react';

/** One labelled fact in a detail page hero's chip row. */
export default function StatChip({
    icon: Icon,
    label,
    value,
}: {
    icon: LucideIcon;
    label: string;
    value: string;
}) {
    return (
        <div className="flex items-center gap-2 rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-900/40">
            <Icon className="size-4 text-gray-400 dark:text-gray-500" />
            <span className="text-xs text-gray-500 dark:text-gray-400">
                {label}
            </span>
            <span className="text-sm font-semibold text-gray-800 dark:text-gray-100">
                {value}
            </span>
        </div>
    );
}
