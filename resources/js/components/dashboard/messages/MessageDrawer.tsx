import { Link } from '@inertiajs/react';
import {
    ArrowUpRight,
    ChevronDown,
    ChevronUp,
    Mail,
    MessageSquare,
    RotateCw,
    X,
} from 'lucide-react';
import { useEffect, useEffectEvent, useState } from 'react';
import type { ReactNode } from 'react';

import CopyValue from '@/components/dashboard/messages/CopyValue';
import MessagePreview from '@/components/dashboard/messages/MessagePreview';
import MessageStatusBadge from '@/components/dashboard/messages/MessageStatusBadge';
import MessageTimeline from '@/components/dashboard/messages/MessageTimeline';
import {
    PROVIDER_LABELS,
    shortUuid,
    STATUS_META,
    TYPE_LABELS,
} from '@/components/dashboard/messages/meta';
import type { MessageRecord } from '@/components/dashboard/messages/types';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';
import { formatDateTime, formatPhone } from '@/lib/dashboard/format';
import dashboardRoutes from '@/routes/dashboard';

interface MessageDrawerProps {
    message: MessageRecord | null;
    /** 1-based place of the message in the rows it was opened from, and how many there are. */
    position: { index: number; total: number } | null;
    onClose: () => void;
    onPrevious: (() => void) | null;
    onNext: (() => void) | null;
}

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="px-5 py-5 sm:px-6">
            <h3 className="mb-3 text-xs font-semibold text-gray-400 uppercase dark:text-gray-500">
                {title}
            </h3>
            {children}
        </section>
    );
}

function Detail({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="flex items-center justify-between gap-4 py-2.5">
            <dt className="shrink-0 text-gray-500 dark:text-gray-400">
                {label}
            </dt>
            <dd className="min-w-0 text-right font-medium text-gray-800 dark:text-gray-100">
                {children}
            </dd>
        </div>
    );
}

function relatedHref(related: NonNullable<MessageRecord['related']>): string {
    return related.kind === 'check-in'
        ? dashboardRoutes.checkIns.show.url(related.uuid)
        : dashboardRoutes.appointments.show.url(related.uuid);
}

/** Moves through the rows with j and k while the drawer is open, unless the user is typing. */
function useStepKeys(
    onPrevious: (() => void) | null,
    onNext: (() => void) | null,
) {
    const onKey = useEffectEvent((event: KeyboardEvent) => {
        const target = event.target as HTMLElement | null;

        if (
            event.metaKey ||
            event.ctrlKey ||
            event.altKey ||
            target?.closest('input, textarea, select, [contenteditable]')
        ) {
            return;
        }

        if (event.key === 'j' && onNext) {
            event.preventDefault();
            onNext();
        } else if (event.key === 'k' && onPrevious) {
            event.preventDefault();
            onPrevious();
        }
    });

    useEffect(() => {
        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, []);
}

function StepButton({
    label,
    shortcut,
    onClick,
    children,
}: {
    label: string;
    shortcut: string;
    onClick: (() => void) | null;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={onClick ?? undefined}
            disabled={onClick === null}
            title={`${label} (${shortcut})`}
            className="rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 disabled:pointer-events-none disabled:opacity-40 dark:text-gray-500 dark:hover:bg-gray-700 dark:hover:text-gray-300"
        >
            {children}
            <span className="sr-only">{label}</span>
        </button>
    );
}

/** The drawer's contents; mounted only while open, so its key handler lives only then. */
function DrawerBody({
    message,
    position,
    onPrevious,
    onNext,
}: Omit<MessageDrawerProps, 'message' | 'onClose'> & {
    message: MessageRecord;
}) {
    useStepKeys(onPrevious, onNext);

    const ChannelIcon = message.channel === 'sms' ? MessageSquare : Mail;
    const recipient =
        message.channel === 'sms'
            ? formatPhone(message.recipient)
            : message.recipient;
    const webhookEvents = message.events.filter((event) => event.fromWebhook);

    return (
        <>
            <header className="flex items-start gap-3 border-b border-gray-100 px-5 pt-5 pb-4 sm:px-6 dark:border-gray-700/60">
                <span
                    className={`mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-full ${STATUS_META[message.status].badge}`}
                >
                    <ChannelIcon className="size-4" aria-hidden />
                </span>
                <div className="min-w-0 grow">
                    <div className="mb-1 flex flex-wrap items-center gap-2">
                        <MessageStatusBadge status={message.status} />
                        <span className="text-xs text-gray-400 dark:text-gray-500">
                            {TYPE_LABELS[message.type]}
                        </span>
                    </div>
                    <SheetTitle className="truncate text-base text-gray-800 dark:text-gray-100">
                        {message.recipientName ?? recipient}
                    </SheetTitle>
                    <SheetDescription className="truncate text-gray-500 dark:text-gray-400">
                        {message.recipientName ? `${recipient} · ` : ''}
                        {formatDateTime(message.sentAt)}
                    </SheetDescription>
                </div>
                <div className="flex shrink-0 items-center gap-0.5">
                    {position && (
                        <span className="mr-1 hidden text-xs text-gray-400 tabular-nums sm:inline dark:text-gray-500">
                            {position.index} / {position.total}
                        </span>
                    )}
                    <StepButton
                        label="Previous message"
                        shortcut="k"
                        onClick={onPrevious}
                    >
                        <ChevronUp className="size-4" />
                    </StepButton>
                    <StepButton
                        label="Next message"
                        shortcut="j"
                        onClick={onNext}
                    >
                        <ChevronDown className="size-4" />
                    </StepButton>
                    <SheetClose className="ml-1 rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:text-gray-500 dark:hover:bg-gray-700 dark:hover:text-gray-300">
                        <X className="size-4" />
                        <span className="sr-only">Close</span>
                    </SheetClose>
                </div>
            </header>

            <div className="grow divide-y divide-gray-100 overflow-y-auto dark:divide-gray-700/60">
                <Section title="Delivery">
                    <MessageTimeline message={message} />
                    {message.status === 'accepted' && (
                        <p className="mt-4 rounded-lg bg-violet-500/8 px-3 py-2 text-xs text-violet-700 dark:text-violet-300">
                            {PROVIDER_LABELS[message.provider]} does not send
                            delivery receipts. Accepted means it took the
                            message for delivery.
                        </p>
                    )}
                </Section>

                <Section title="Message">
                    <MessagePreview message={message} />
                </Section>

                <Section title="Details">
                    <dl className="divide-y divide-gray-100 text-sm dark:divide-gray-700/60">
                        <Detail label="Message ID">
                            <CopyValue
                                value={message.uuid}
                                label="Copy message ID"
                            />
                        </Detail>
                        <Detail label="Provider">
                            {PROVIDER_LABELS[message.provider]}
                        </Detail>
                        <Detail label="Provider ID">
                            {message.providerMessageId === null ? (
                                <span className="text-gray-400 dark:text-gray-500">
                                    {message.provider === 'microsoft'
                                        ? 'Not returned by Microsoft 365'
                                        : 'Not assigned yet'}
                                </span>
                            ) : (
                                <CopyValue
                                    value={message.providerMessageId}
                                    label="Copy provider ID"
                                />
                            )}
                        </Detail>
                        <Detail label="Sent from">
                            {message.channel === 'sms'
                                ? formatPhone(message.sender)
                                : message.sender}
                        </Detail>
                        <Detail label="Attempts">{message.attempts}</Detail>
                        {message.related && (
                            <Detail
                                label={
                                    message.related.kind === 'check-in'
                                        ? 'Check-in'
                                        : 'Appointment'
                                }
                            >
                                <Link
                                    href={relatedHref(message.related)}
                                    className="font-mono text-xs text-brand-green hover:underline"
                                >
                                    {message.related.referenceNumber}
                                </Link>
                            </Detail>
                        )}
                    </dl>
                    {webhookEvents.length > 0 && (
                        <div className="mt-4">
                            <div className="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                                Webhook callbacks
                            </div>
                            <ul className="divide-y divide-gray-100 rounded-lg border border-gray-100 text-sm dark:divide-gray-700/60 dark:border-gray-700/60">
                                {webhookEvents.map((event) => (
                                    <li
                                        key={event.uuid}
                                        className="flex items-center justify-between gap-3 px-3 py-2"
                                    >
                                        <span className="flex items-center gap-2 text-gray-700 dark:text-gray-200">
                                            <span
                                                className={`size-1.5 rounded-full ${STATUS_META[event.type].dot}`}
                                                aria-hidden
                                            />
                                            {STATUS_META[event.type].label}
                                        </span>
                                        <CopyValue
                                            value={event.uuid}
                                            display={shortUuid(event.uuid)}
                                            label="Copy callback ID"
                                        />
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </Section>
            </div>

            <footer className="flex items-center justify-between gap-3 border-t border-gray-100 px-5 py-4 sm:px-6 dark:border-gray-700/60">
                <span className="hidden text-xs text-gray-400 sm:inline dark:text-gray-500">
                    <kbd className="font-sans">j</kbd> /{' '}
                    <kbd className="font-sans">k</kbd> to move,{' '}
                    <kbd className="font-sans">esc</kbd> to close
                </span>
                <div className="flex gap-2 sm:ml-auto">
                    {message.related && (
                        <Link
                            href={relatedHref(message.related)}
                            className="btn gap-1.5 border-gray-200 bg-white text-gray-800 hover:border-gray-300 dark:border-gray-700/60 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-gray-600"
                        >
                            Open{' '}
                            {message.related.kind === 'check-in'
                                ? 'check-in'
                                : 'appointment'}
                            <ArrowUpRight className="size-4" aria-hidden />
                        </Link>
                    )}
                    {/* Resending lands with the backend pass. */}
                    <button
                        type="button"
                        disabled
                        title="Resending is not available yet"
                        className="btn gap-1.5 bg-gray-900 text-gray-100 hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-gray-100 dark:text-gray-800 dark:hover:bg-white"
                    >
                        <RotateCw className="size-4" aria-hidden />
                        Resend
                    </button>
                </div>
            </footer>
        </>
    );
}

/** One message's delivery, preview and ids, in a panel beside the table. */
export default function MessageDrawer({
    message,
    position,
    onClose,
    onPrevious,
    onNext,
}: MessageDrawerProps) {
    // Keeps the last message on screen while the panel slides out after closing.
    const [shown, setShown] = useState(message);

    if (message !== null && message !== shown) {
        setShown(message);
    }

    return (
        <Sheet
            open={message !== null}
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                }
            }}
        >
            <SheetContent className="dashboard">
                {shown && (
                    <DrawerBody
                        message={shown}
                        position={position}
                        onPrevious={onPrevious}
                        onNext={onNext}
                    />
                )}
            </SheetContent>
        </Sheet>
    );
}
