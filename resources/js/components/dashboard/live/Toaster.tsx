import { Link } from '@inertiajs/react';
import { Truck, X } from 'lucide-react';

export interface LiveToast {
    id: string;
    title: string;
    body: string;
    href: string;
}

/** Bottom-right stack of arrival toasts, newest at the bottom. Each one links to its record. */
export default function Toaster({
    toasts,
    onDismiss,
}: {
    toasts: LiveToast[];
    onDismiss: (id: string) => void;
}) {
    return (
        <div
            className="pointer-events-none fixed right-4 bottom-4 z-50 flex w-[calc(100%-2rem)] max-w-sm flex-col gap-3"
            role="region"
            aria-label="New check-ins"
            aria-live="polite"
        >
            {toasts.map((toast) => (
                <div
                    key={toast.id}
                    className="pointer-events-auto flex animate-in items-start gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-lg duration-300 fade-in slide-in-from-bottom-4 dark:border-gray-700/60 dark:bg-gray-800"
                >
                    <div className="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-green/10 text-brand-green">
                        <Truck className="size-4" />
                    </div>
                    <Link
                        href={toast.href}
                        onClick={() => onDismiss(toast.id)}
                        className="min-w-0 grow"
                    >
                        <div className="text-sm font-semibold text-gray-800 dark:text-gray-100">
                            {toast.title}
                        </div>
                        <div className="truncate text-sm text-gray-500 dark:text-gray-400">
                            {toast.body}
                        </div>
                    </Link>
                    <button
                        type="button"
                        onClick={() => onDismiss(toast.id)}
                        className="shrink-0 text-gray-400 hover:text-gray-500 dark:text-gray-500 dark:hover:text-gray-400"
                    >
                        <span className="sr-only">Dismiss</span>
                        <X className="size-4" />
                    </button>
                </div>
            ))}
        </div>
    );
}
