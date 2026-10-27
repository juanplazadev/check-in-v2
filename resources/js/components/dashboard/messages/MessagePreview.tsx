import { useState } from 'react';

import type { MessageRecord } from '@/components/dashboard/messages/types';
import { formatPhone, formatTime } from '@/lib/dashboard/format';

type EmailView = 'rendered' | 'text';

const VIEW_LABELS: Record<EmailView, string> = {
    rendered: 'Rendered',
    text: 'Plain text',
};

function EmailPreview({ message }: { message: MessageRecord }) {
    const [view, setView] = useState<EmailView>(
        message.html === null ? 'text' : 'rendered',
    );

    return (
        <div className="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700/60">
            <div className="space-y-1 border-b border-gray-200 bg-gray-50 px-4 py-3 text-xs dark:border-gray-700/60 dark:bg-gray-900/30">
                <div className="flex gap-2">
                    <span className="w-12 shrink-0 text-gray-400 dark:text-gray-500">
                        From
                    </span>
                    <span className="truncate text-gray-700 dark:text-gray-200">
                        {message.sender}
                    </span>
                </div>
                <div className="flex gap-2">
                    <span className="w-12 shrink-0 text-gray-400 dark:text-gray-500">
                        To
                    </span>
                    <span className="truncate text-gray-700 dark:text-gray-200">
                        {message.recipientName
                            ? `${message.recipientName} <${message.recipient}>`
                            : message.recipient}
                    </span>
                </div>
                <div className="flex items-end justify-between gap-3 pt-1">
                    <span className="text-sm font-semibold text-gray-800 dark:text-gray-100">
                        {message.subject}
                    </span>
                    {message.html !== null && (
                        <div
                            role="group"
                            aria-label="Preview format"
                            className="flex shrink-0 rounded-lg bg-gray-200/70 p-0.5 dark:bg-gray-700/60"
                        >
                            {(['rendered', 'text'] as const).map((option) => (
                                <button
                                    key={option}
                                    type="button"
                                    aria-pressed={view === option}
                                    onClick={() => setView(option)}
                                    className={`rounded-md px-2 py-0.5 text-xs font-medium transition ${
                                        view === option
                                            ? 'bg-white text-gray-800 shadow-xs dark:bg-gray-800 dark:text-gray-100'
                                            : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'
                                    }`}
                                >
                                    {VIEW_LABELS[option]}
                                </button>
                            ))}
                        </div>
                    )}
                </div>
            </div>
            {view === 'rendered' && message.html !== null ? (
                // An empty sandbox: no scripts, forms or same-origin access, whatever the body holds.
                <iframe
                    title={`Preview of ${message.subject ?? 'the email'}`}
                    sandbox=""
                    srcDoc={message.html}
                    className="block h-96 w-full bg-white"
                />
            ) : (
                <pre className="max-h-96 overflow-auto px-4 py-3 font-sans text-sm whitespace-pre-wrap text-gray-700 dark:text-gray-200">
                    {message.body}
                </pre>
            )}
        </div>
    );
}

function SmsPreview({ message }: { message: MessageRecord }) {
    return (
        <div className="rounded-xl border border-gray-200 bg-gray-50 px-4 py-5 dark:border-gray-700/60 dark:bg-gray-900/30">
            <div className="mb-4 text-center text-xs text-gray-400 dark:text-gray-500">
                {formatPhone(message.sender)} to{' '}
                {formatPhone(message.recipient)}
            </div>
            <div className="flex flex-col items-end gap-1">
                <div className="max-w-[85%] rounded-2xl rounded-br-md bg-brand-green px-3.5 py-2 text-sm whitespace-pre-wrap text-white shadow-xs">
                    {message.body}
                </div>
                <div className="text-[11px] text-gray-400 dark:text-gray-500">
                    {formatTime(message.sentAt)}
                    {message.segments !== null &&
                        ` · ${message.segments} ${message.segments === 1 ? 'segment' : 'segments'} · ${message.body.length} characters`}
                </div>
            </div>
        </div>
    );
}

/** The message as the recipient saw it. */
export default function MessagePreview({
    message,
}: {
    message: MessageRecord;
}) {
    return message.channel === 'sms' ? (
        <SmsPreview message={message} />
    ) : (
        // Keyed so the format toggle resets when the drawer moves to another message.
        <EmailPreview key={message.uuid} message={message} />
    );
}
