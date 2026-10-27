import { Webhook } from 'lucide-react';

import {
    FINAL_STATUSES,
    PROVIDER_LABELS,
    shortUuid,
    STATUS_META,
} from '@/components/dashboard/messages/meta';
import type {
    MessageEvent,
    MessageRecord,
} from '@/components/dashboard/messages/types';
import { formatDateTime } from '@/lib/dashboard/format';

function eventTitle(event: MessageEvent, message: MessageRecord): string {
    const provider = PROVIDER_LABELS[message.provider];

    switch (event.type) {
        case 'queued':
            return 'Queued to send';
        case 'sent':
            return `Handed to ${provider}`;
        case 'accepted':
            return `Accepted by ${provider}`;
        case 'delivered':
            return message.channel === 'sms'
                ? 'Delivered to the phone'
                : 'Delivered to the inbox';
        case 'opened':
            return 'Opened by the recipient';
        case 'bounced':
            return 'Bounced';
        case 'failed':
            return 'Failed';
    }
}

/** A message's delivery steps, oldest first, with the step still expected shown faded. */
export default function MessageTimeline({
    message,
}: {
    message: MessageRecord;
}) {
    const isWaiting = !FINAL_STATUSES.has(message.status);

    return (
        <ol>
            {message.events.map((event) => (
                <li key={event.uuid} className="relative pb-5 pl-6 last:pb-0">
                    <span
                        className="absolute top-3 bottom-0 left-[3px] w-px bg-gray-200 dark:bg-gray-700 [li:last-child>&]:hidden"
                        aria-hidden="true"
                    />
                    <span
                        className={`absolute top-1.5 left-0 size-2 rounded-full ring-4 ring-white dark:ring-gray-800 ${STATUS_META[event.type].dot}`}
                        aria-hidden="true"
                    />
                    <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <span className="text-sm font-medium text-gray-800 dark:text-gray-100">
                            {eventTitle(event, message)}
                        </span>
                        {event.fromWebhook && (
                            <span
                                className="inline-flex items-center gap-1 rounded-md bg-gray-100 px-1.5 py-0.5 font-mono text-[11px] text-gray-500 dark:bg-gray-700/60 dark:text-gray-400"
                                title={`Webhook ${event.uuid}`}
                            >
                                <Webhook className="size-3" aria-hidden />
                                {shortUuid(event.uuid)}
                            </span>
                        )}
                    </div>
                    {event.reason !== null && (
                        <p className="mt-1.5 rounded-lg bg-rose-50 px-3 py-2 font-mono text-xs break-words text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">
                            {event.reason}
                        </p>
                    )}
                    <div className="text-xs text-gray-400 dark:text-gray-500">
                        {formatDateTime(event.occurredAt)}
                    </div>
                </li>
            ))}
            {isWaiting && (
                <li className="relative pl-6">
                    <span
                        className="absolute top-1.5 left-0 size-2 rounded-full border border-dashed border-gray-300 dark:border-gray-600"
                        aria-hidden="true"
                    />
                    <span className="text-sm text-gray-400 italic dark:text-gray-500">
                        {message.status === 'queued'
                            ? 'Waiting for a worker'
                            : 'Waiting for a delivery receipt'}
                    </span>
                </li>
            )}
        </ol>
    );
}
