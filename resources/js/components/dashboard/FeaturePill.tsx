import type { LucideIcon } from 'lucide-react';
import { Check, X } from 'lucide-react';

/** A location feature, such as check-ins, marked enabled or disabled. */
export default function FeaturePill({
    icon: Icon,
    label,
    enabled,
}: {
    icon: LucideIcon;
    label: string;
    enabled: boolean;
}) {
    const StateIcon = enabled ? Check : X;

    return (
        <span
            className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ${
                enabled
                    ? 'bg-brand-green/12 text-brand-green'
                    : 'bg-gray-100 text-gray-500 dark:bg-gray-700/60 dark:text-gray-400'
            }`}
        >
            <Icon className="size-3.5" aria-hidden />
            {label}
            <StateIcon
                className="size-3"
                aria-label={enabled ? 'enabled' : 'disabled'}
            />
        </span>
    );
}
