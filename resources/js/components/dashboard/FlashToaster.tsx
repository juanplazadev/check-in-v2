import { router } from '@inertiajs/react';
import { CheckCircle2, CircleAlert, X } from 'lucide-react';
import { useEffect, useState } from 'react';

const TOAST_LIFETIME_MS = 6000;

interface FlashToast {
    id: number;
    type: 'success' | 'error';
    message: string;
}

/**
 * Shows the `toast` the server flashes with Inertia::flash(), for actions whose result is not
 * otherwise visible, such as a delete that redirects away from the record. Top right, so it does
 * not collide with the live check-in toasts at the bottom. Lives in the persistent dashboard
 * layout, so the listener survives the redirect that carries the flash.
 */
export default function FlashToaster() {
    const [toasts, setToasts] = useState<FlashToast[]>([]);

    useEffect(
        () =>
            router.on('flash', (event) => {
                const toast = event.detail.flash.toast;

                if (toast) {
                    setToasts((previous) => [
                        ...previous,
                        { id: Date.now(), ...toast },
                    ]);
                }
            }),
        [],
    );

    useEffect(() => {
        if (toasts.length === 0) {
            return;
        }

        const timeout = window.setTimeout(
            () => setToasts((previous) => previous.slice(1)),
            TOAST_LIFETIME_MS,
        );

        return () => window.clearTimeout(timeout);
    }, [toasts]);

    const dismiss = (id: number) =>
        setToasts((previous) => previous.filter((toast) => toast.id !== id));

    return (
        <div
            className="pointer-events-none fixed top-4 right-4 z-50 flex w-[calc(100%-2rem)] max-w-sm flex-col gap-3"
            role="status"
            aria-live="polite"
        >
            {toasts.map((toast) => (
                <div
                    key={toast.id}
                    className="pointer-events-auto flex animate-in items-start gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-lg duration-300 fade-in slide-in-from-top-4 dark:border-gray-700/60 dark:bg-gray-800"
                >
                    <div
                        className={`flex size-9 shrink-0 items-center justify-center rounded-full ${
                            toast.type === 'success'
                                ? 'bg-brand-green/10 text-brand-green'
                                : 'bg-red-500/10 text-red-500'
                        }`}
                    >
                        {toast.type === 'success' ? (
                            <CheckCircle2 className="size-4" />
                        ) : (
                            <CircleAlert className="size-4" />
                        )}
                    </div>
                    <p className="min-w-0 grow self-center text-sm font-medium text-gray-800 dark:text-gray-100">
                        {toast.message}
                    </p>
                    <button
                        type="button"
                        onClick={() => dismiss(toast.id)}
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
