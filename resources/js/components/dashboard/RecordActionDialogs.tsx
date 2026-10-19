import { Mail } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';

import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

/**
 * The menus, buttons and confirmation dialogs the record detail toolbars (check-ins, appointments)
 * share. Each dialog takes its copy as props, so the record type stays out of here.
 */

export const MENU_CLASSES =
    'dashboard rounded-lg border border-gray-200 bg-white p-1.5 shadow-lg ring-0 dark:border-gray-700/60 dark:bg-gray-800';

export const SECONDARY_BUTTON =
    'btn gap-2 border-gray-200 bg-white text-gray-600 hover:border-gray-300 data-[state=open]:border-gray-300 dark:border-gray-700/60 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-gray-600 dark:data-[state=open]:border-gray-600';

const NEUTRAL_CONFIRM_BUTTON =
    'btn bg-gray-900 text-gray-100 hover:bg-gray-800 disabled:opacity-50 dark:bg-gray-100 dark:text-gray-800 dark:hover:bg-white';

const DANGER_CONFIRM_BUTTON =
    'btn bg-rose-500 text-white hover:bg-rose-600 disabled:opacity-50';

export function CopyItem({
    icon,
    label,
    copied,
    onCopy,
}: {
    icon: ReactNode;
    label: string;
    copied: boolean;
    onCopy: () => void;
}) {
    return (
        <DropdownMenuItem
            className="rounded-md"
            onSelect={(event) => {
                // Keep the menu open so the "Copied" confirmation is visible.
                event.preventDefault();
                onCopy();
            }}
        >
            {icon}
            <span className="flex-1">{label}</span>
            {copied && (
                <span className="text-xs font-medium text-brand-green">
                    Copied
                </span>
            )}
        </DropdownMenuItem>
    );
}

/**
 * The PDF menu's "Email to driver" item. It stays open on select so the sending, sent and error
 * states are seen, and is disabled when the driver has no email on file.
 */
export function EmailPdfItem({
    email,
    emailing,
    emailed,
    error,
    onEmail,
}: {
    email: string | null;
    emailing: boolean;
    emailed: boolean;
    error: string | null;
    onEmail: () => void;
}) {
    const detail =
        email === null
            ? 'No email on file'
            : emailed
              ? `Sent to ${email}`
              : email;

    return (
        <DropdownMenuItem
            className="items-start rounded-md"
            disabled={email === null || emailing}
            onSelect={(event) => {
                event.preventDefault();
                onEmail();
            }}
        >
            <Mail className="mt-0.5" />
            <span className="min-w-0">
                <span className="block">
                    {emailing ? 'Sending…' : 'Email to driver'}
                </span>
                <span
                    className={`block truncate text-xs ${emailed ? 'text-brand-green' : 'text-gray-500 dark:text-gray-400'}`}
                >
                    {detail}
                </span>
                {error !== null && (
                    <span className="block text-xs text-red-500">{error}</span>
                )}
            </span>
        </DropdownMenuItem>
    );
}

/** Confirms a status change that takes no input, such as reopening or restoring. */
export function StatusConfirmDialog({
    open,
    onOpenChange,
    title,
    description,
    keepLabel,
    confirmLabel,
    busy,
    onConfirm,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: string;
    keepLabel: string;
    confirmLabel: string;
    busy: boolean;
    onConfirm: () => void;
}) {
    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent className="dashboard">
                <AlertDialogHeader>
                    <AlertDialogTitle>{title}</AlertDialogTitle>
                    <AlertDialogDescription>
                        {description}
                    </AlertDialogDescription>
                </AlertDialogHeader>

                <AlertDialogFooter>
                    <AlertDialogCancel asChild>
                        <button
                            type="button"
                            disabled={busy}
                            className={SECONDARY_BUTTON}
                        >
                            {keepLabel}
                        </button>
                    </AlertDialogCancel>
                    <button
                        type="button"
                        disabled={busy}
                        onClick={onConfirm}
                        className={NEUTRAL_CONFIRM_BUTTON}
                    >
                        {confirmLabel}
                    </button>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

/** Confirms a status change that takes a written reason, such as canceling or archiving. */
export function ReasonDialog({
    open,
    onOpenChange,
    id,
    title,
    description,
    label,
    placeholder,
    required,
    keepLabel,
    confirmLabel,
    tone,
    busy,
    error,
    onConfirm,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** The textarea's id, unique on the page. */
    id: string;
    title: string;
    description: string;
    label: string;
    placeholder: string;
    /** A required reason keeps the confirm button disabled until one is written. */
    required: boolean;
    keepLabel: string;
    confirmLabel: string;
    tone: 'danger' | 'neutral';
    busy: boolean;
    /** The server's refusal, e.g. a missing reason or a record in the wrong status. */
    error: string | null;
    /** Called with the trimmed reason, empty when an optional one was not given. */
    onConfirm: (reason: string) => void;
}) {
    const [reason, setReason] = useState('');

    const changeOpen = (next: boolean) => {
        onOpenChange(next);

        if (!next) {
            setReason('');
        }
    };

    return (
        <AlertDialog open={open} onOpenChange={changeOpen}>
            <AlertDialogContent className="dashboard">
                <AlertDialogHeader>
                    <AlertDialogTitle>{title}</AlertDialogTitle>
                    <AlertDialogDescription>
                        {description}
                    </AlertDialogDescription>
                </AlertDialogHeader>

                <Field data-invalid={error !== null || undefined}>
                    <FieldLabel htmlFor={id}>{label}</FieldLabel>
                    <Textarea
                        id={id}
                        rows={3}
                        maxLength={500}
                        placeholder={placeholder}
                        value={reason}
                        aria-invalid={error !== null}
                        onChange={(event) => setReason(event.target.value)}
                    />
                    {error !== null && <FieldError>{error}</FieldError>}
                </Field>

                <AlertDialogFooter>
                    <AlertDialogCancel asChild>
                        <button
                            type="button"
                            disabled={busy}
                            className={SECONDARY_BUTTON}
                        >
                            {keepLabel}
                        </button>
                    </AlertDialogCancel>
                    <button
                        type="button"
                        disabled={busy || (required && reason.trim() === '')}
                        onClick={() => onConfirm(reason.trim())}
                        className={
                            tone === 'danger'
                                ? DANGER_CONFIRM_BUTTON
                                : NEUTRAL_CONFIRM_BUTTON
                        }
                    >
                        {confirmLabel}
                    </button>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

/**
 * Confirms a permanent delete: what goes and what stays, a required reason, and the reference
 * number typed back so the delete cannot happen by a stray click.
 */
export function DeleteRecordDialog({
    open,
    onOpenChange,
    referenceNumber,
    title,
    description,
    impact,
    placeholder,
    keepLabel,
    deleting,
    error,
    onConfirm,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    referenceNumber: string;
    title: string;
    description: string;
    /** The summary of what the delete erases and keeps, usually `ImpactList`s. */
    impact: ReactNode;
    placeholder: string;
    keepLabel: string;
    deleting: boolean;
    /** The server's refusal, e.g. a missing or too long reason. */
    error: string | null;
    /** Called with the trimmed reason. */
    onConfirm: (reason: string) => void;
}) {
    const [reason, setReason] = useState('');
    const [confirmation, setConfirmation] = useState('');

    const changeOpen = (next: boolean) => {
        onOpenChange(next);

        if (!next) {
            setReason('');
            setConfirmation('');
        }
    };

    const ready =
        reason.trim() !== '' &&
        confirmation.trim() === referenceNumber &&
        !deleting;

    return (
        <AlertDialog open={open} onOpenChange={changeOpen}>
            <AlertDialogContent className="dashboard max-h-[90vh] overflow-y-auto">
                <AlertDialogHeader>
                    <AlertDialogTitle>{title}</AlertDialogTitle>
                    <AlertDialogDescription>
                        {description}
                    </AlertDialogDescription>
                </AlertDialogHeader>

                <div className="space-y-4 text-sm">{impact}</div>

                <Field data-invalid={error !== null || undefined}>
                    <FieldLabel htmlFor="delete_reason">Reason</FieldLabel>
                    <Textarea
                        id="delete_reason"
                        rows={3}
                        maxLength={500}
                        placeholder={placeholder}
                        value={reason}
                        aria-invalid={error !== null}
                        onChange={(event) => setReason(event.target.value)}
                    />
                    {error !== null && <FieldError>{error}</FieldError>}
                </Field>

                <Field>
                    <FieldLabel htmlFor="delete_confirmation">
                        Type{' '}
                        <span className="font-mono font-semibold">
                            {referenceNumber}
                        </span>{' '}
                        to confirm
                    </FieldLabel>
                    <Input
                        id="delete_confirmation"
                        autoComplete="off"
                        value={confirmation}
                        onChange={(event) =>
                            setConfirmation(event.target.value)
                        }
                    />
                </Field>

                <AlertDialogFooter>
                    <AlertDialogCancel asChild>
                        <button
                            type="button"
                            disabled={deleting}
                            className={SECONDARY_BUTTON}
                        >
                            {keepLabel}
                        </button>
                    </AlertDialogCancel>
                    <button
                        type="button"
                        disabled={!ready}
                        onClick={() => onConfirm(reason.trim())}
                        className={DANGER_CONFIRM_BUTTON}
                    >
                        Delete permanently
                    </button>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

export function plural(count: number, one: string, many: string): string {
    return `${count} ${count === 1 ? one : many}`;
}

/**
 * One section of a delete dialog's impact summary. Items that do not apply to this record are
 * muted rather than hidden, so every kind of attached record is always listed.
 */
export function ImpactList({
    title,
    tone,
    items,
}: {
    title: string;
    tone: 'danger' | 'neutral';
    items: { label: string; active: boolean }[];
}) {
    return (
        <div
            className={
                tone === 'danger'
                    ? 'rounded-lg border border-rose-200 bg-rose-50 p-3 dark:border-rose-500/30 dark:bg-rose-500/10'
                    : 'rounded-lg border border-gray-200 p-3 dark:border-gray-700/60'
            }
        >
            <div
                className={
                    tone === 'danger'
                        ? 'mb-2 text-xs font-semibold text-rose-600 uppercase dark:text-rose-400'
                        : 'mb-2 text-xs font-semibold text-gray-500 uppercase dark:text-gray-400'
                }
            >
                {title}
            </div>
            <ul className="list-disc space-y-1 pl-5">
                {items.map((item) => (
                    <li
                        key={item.label}
                        className={
                            item.active
                                ? 'text-gray-800 dark:text-gray-100'
                                : 'text-gray-400 dark:text-gray-500'
                        }
                    >
                        {item.label}
                    </li>
                ))}
            </ul>
        </div>
    );
}
