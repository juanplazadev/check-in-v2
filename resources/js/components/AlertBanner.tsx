import type { ReactNode } from 'react';

interface AlertBannerProps {
    type?: 'warning' | 'error' | 'success' | string;
    open?: boolean;
    title?: string;
    className?: string;
    onClose: () => void;
    children: ReactNode;
    /** Screen-reader text for the close button, in the page's language. */
    closeLabel?: string;
}

export default function AlertBanner({
    type = '',
    open = false,
    title,
    className,
    onClose,
    children,
    closeLabel = 'Close',
}: AlertBannerProps) {
    if (!open) {
        return null;
    }

    const styles = {
        warning: {
            wrapper:
                'bg-amber-50 border border-amber-200 dark:bg-amber-900/20 dark:border-amber-800/40',
            icon: 'text-amber-700 dark:text-amber-400',
            text: 'text-amber-800 dark:text-amber-300',
        },
        error: {
            wrapper:
                'bg-red-50 border border-red-200 dark:bg-red-900/20 dark:border-red-800/40',
            icon: 'text-red-700 dark:text-red-400',
            text: 'text-red-800 dark:text-red-300',
        },
        success: {
            wrapper:
                'bg-green-50 border border-green-200 dark:bg-green-900/20 dark:border-green-800/40',
            icon: 'text-green-700 dark:text-green-400',
            text: 'text-green-800 dark:text-green-300',
        },
    }[type] ?? {
        wrapper:
            'bg-violet-50 border border-violet-200 dark:bg-violet-900/20 dark:border-violet-800/40',
        icon: 'text-violet-700 dark:text-violet-400',
        text: 'text-violet-800 dark:text-violet-300',
    };

    return (
        <div
            role="alert"
            className={`flex items-start gap-3 rounded-lg px-4 py-3 ${styles.wrapper} ${className ?? ''}`}
        >
            {/* Icon */}
            <div className={`mt-0.5 shrink-0 ${styles.icon}`}>
                {type === 'error' && (
                    <svg className="h-4 w-4 fill-current" viewBox="0 0 16 16">
                        <path d="M8 0C3.6 0 0 3.6 0 8s3.6 8 8 8 8-3.6 8-8-3.6-8-8-8zm3.5 10.1l-1.4 1.4L8 9.4l-2.1 2.1-1.4-1.4L6.6 8 4.5 5.9l1.4-1.4L8 6.6l2.1-2.1 1.4 1.4L9.4 8l2.1 2.1z" />
                    </svg>
                )}
                {type === 'warning' && (
                    <svg className="h-4 w-4 fill-current" viewBox="0 0 16 16">
                        <path d="M8 0C3.6 0 0 3.6 0 8s3.6 8 8 8 8-3.6 8-8-3.6-8-8-8zm0 12c-.6 0-1-.4-1-1s.4-1 1-1 1 .4 1 1-.4 1-1 1zm1-3H7V4h2v5z" />
                    </svg>
                )}
                {type === 'success' && (
                    <svg className="h-4 w-4 fill-current" viewBox="0 0 16 16">
                        <path d="M8 0C3.6 0 0 3.6 0 8s3.6 8 8 8 8-3.6 8-8-3.6-8-8-8zM7 11.4L3.6 8 5 6.6l2 2 4-4L12.4 6 7 11.4z" />
                    </svg>
                )}
            </div>

            {/* Content */}
            <div className={`flex-1 text-sm ${styles.text}`}>
                {title && <p className="font-medium">{title}</p>}
                <p className={title ? 'mt-0.5 opacity-85' : ''}>{children}</p>
            </div>

            {/* Close */}
            <button
                onClick={onClose}
                className={`mt-0.5 shrink-0 cursor-pointer opacity-50 transition-opacity hover:opacity-75 ${styles.icon}`}
            >
                <span className="sr-only">{closeLabel}</span>
                <svg className="h-3.5 w-3.5 fill-current" viewBox="0 0 16 16">
                    <path d="M7.95 6.536l4.242-4.243a1 1 0 111.415 1.414L9.364 7.95l4.243 4.242a1 1 0 11-1.415 1.415L7.95 9.364l-4.243 4.243a1 1 0 01-1.414-1.415L6.536 7.95 2.293 3.707a1 1 0 011.414-1.414L7.95 6.536z" />
                </svg>
            </button>
        </div>
    );
}
