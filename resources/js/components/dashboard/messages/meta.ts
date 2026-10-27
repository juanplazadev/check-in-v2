import type {
    MessageProvider,
    MessageStatus,
    MessageType,
} from '@/components/dashboard/messages/types';

export const STATUS_META: Record<
    MessageStatus,
    { label: string; badge: string; dot: string; description: string }
> = {
    queued: {
        label: 'Queued',
        badge: 'bg-gray-500/12 text-gray-600 dark:text-gray-300',
        dot: 'bg-gray-400',
        description: 'Waiting for a queue worker to send it.',
    },
    sent: {
        label: 'Sent',
        badge: 'bg-blue-500/12 text-blue-700 dark:text-blue-400',
        dot: 'bg-blue-500',
        description: 'Handed to the provider. Waiting for a delivery receipt.',
    },
    accepted: {
        label: 'Accepted',
        badge: 'bg-violet-500/12 text-violet-700 dark:text-violet-300',
        dot: 'bg-violet-500',
        description:
            'The provider accepted the message. It does not report delivery, so this is the last status it will get.',
    },
    delivered: {
        label: 'Delivered',
        badge: 'bg-brand-green/12 text-brand-green',
        dot: 'bg-brand-green',
        description: "The recipient's server or carrier took the message.",
    },
    opened: {
        label: 'Opened',
        badge: 'bg-emerald-500/12 text-emerald-700 dark:text-emerald-400',
        dot: 'bg-emerald-500',
        description: 'The recipient opened the email at least once.',
    },
    bounced: {
        label: 'Bounced',
        badge: 'bg-amber-500/15 text-amber-700 dark:text-amber-400',
        dot: 'bg-amber-500',
        description:
            "The recipient's server refused it. The address is probably wrong or full.",
    },
    failed: {
        label: 'Failed',
        badge: 'bg-rose-500/12 text-rose-700 dark:text-rose-400',
        dot: 'bg-rose-500',
        description: 'The provider could not send it.',
    },
};

export const TYPE_LABELS: Record<MessageType, string> = {
    'check-in-received': 'Check-in received',
    'check-in-copy': 'Check-in copy',
    'appointment-booked': 'Appointment booked',
    'appointment-reminder': 'Appointment reminder',
    'appointment-cancelation': 'Appointment cancelation',
    document: 'Document',
};

export const PROVIDER_LABELS: Record<MessageProvider, string> = {
    mailgun: 'Mailgun',
    microsoft: 'Microsoft 365',
    sms: 'SMS carrier',
};

/** Statuses after which nothing else is expected. */
export const FINAL_STATUSES = new Set<MessageStatus>([
    'accepted',
    'delivered',
    'opened',
    'bounced',
    'failed',
]);

/** The first eight characters, enough to tell rows apart at a glance. */
export function shortUuid(uuid: string): string {
    return uuid.slice(0, 8);
}
