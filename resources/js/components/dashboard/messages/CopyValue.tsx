import { Check, Copy } from 'lucide-react';
import { useEffect, useState } from 'react';

/** How long the check mark stays after a copy, in milliseconds. */
const COPIED_FOR = 1500;

/** A monospace id with a copy button; shows `display` but copies `value`. */
export default function CopyValue({
    value,
    display = value,
    label = 'Copy',
}: {
    value: string;
    display?: string;
    label?: string;
}) {
    const [copied, setCopied] = useState(false);

    useEffect(() => {
        if (!copied) {
            return;
        }

        const timer = window.setTimeout(() => setCopied(false), COPIED_FOR);

        return () => window.clearTimeout(timer);
    }, [copied]);

    return (
        <span className="inline-flex max-w-full items-center gap-1">
            <span className="truncate font-mono text-xs" title={value}>
                {display}
            </span>
            <button
                type="button"
                onClick={(event) => {
                    event.stopPropagation();
                    void navigator.clipboard
                        ?.writeText(value)
                        .then(() => setCopied(true));
                }}
                className="shrink-0 rounded p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:text-gray-500 dark:hover:bg-gray-700 dark:hover:text-gray-300"
            >
                {copied ? (
                    <Check className="size-3.5 text-brand-green" />
                ) : (
                    <Copy className="size-3.5" />
                )}
                <span className="sr-only">{copied ? 'Copied' : label}</span>
            </button>
        </span>
    );
}
