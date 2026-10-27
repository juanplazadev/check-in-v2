/**
 * The contract for the messages page. Mock rows fill it for now; the backend pass sends the same
 * shapes as page props.
 */

export type MessageChannel = 'email' | 'sms';

/**
 * Mailgun reports delivery through webhooks. Microsoft Graph `sendMail` only answers 202, so its
 * messages stop at accepted. The SMS carrier is not chosen yet.
 */
export type MessageProvider = 'mailgun' | 'microsoft' | 'sms';

/**
 * Where a message is in its life, newest step wins. `accepted` is final for providers that send no
 * receipts; `opened` is email only.
 */
export type MessageStatus =
    | 'queued'
    | 'sent'
    | 'accepted'
    | 'delivered'
    | 'opened'
    | 'bounced'
    | 'failed';

/** What the message was about, from the notification that sent it. */
export type MessageType =
    | 'check-in-received'
    | 'check-in-copy'
    | 'appointment-booked'
    | 'appointment-reminder'
    | 'appointment-cancelation'
    | 'document';

/** One step in a message's delivery, from the app or from a provider webhook. */
export interface MessageEvent {
    /** Our id for the step; for webhooks it is the stored callback's UUID. */
    uuid: string;
    /** The status the message reached at this step. */
    type: MessageStatus;
    occurredAt: string;
    /** The provider's reason on a failure, such as "550 5.1.1 Mailbox unavailable". */
    reason: string | null;
    /** True when this step arrived as a provider webhook rather than from the app. */
    fromWebhook: boolean;
}

export interface MessageRelated {
    kind: 'check-in' | 'appointment';
    uuid: string;
    referenceNumber: string;
}

export interface MessageRecord {
    uuid: string;
    channel: MessageChannel;
    provider: MessageProvider;
    type: MessageType;
    status: MessageStatus;
    /** An email address, or an E.164 number for SMS. */
    recipient: string;
    recipientName: string | null;
    /** The sending address or number. */
    sender: string;
    /** Email only. */
    subject: string | null;
    /** The text body: the SMS itself, or the email's plain-text part. */
    body: string;
    /** Email only: the rendered HTML, shown in a sandboxed frame. */
    html: string | null;
    /** SMS only: how many 160-character parts the carrier billed. */
    segments: number | null;
    /** The provider's id for the message, once it accepted it. */
    providerMessageId: string | null;
    attempts: number;
    sentAt: string;
    related: MessageRelated | null;
    /** Oldest first. */
    events: MessageEvent[];
}
