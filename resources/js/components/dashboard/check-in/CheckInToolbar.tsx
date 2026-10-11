import { router, usePage } from '@inertiajs/react';
import {
    Archive,
    ArchiveRestore,
    Ban,
    CheckCircle2,
    ChevronDown,
    Copy,
    Eye,
    FileText,
    Fingerprint,
    Link2,
    Mail,
    MessageSquare,
    MoreHorizontal,
    Pencil,
    Printer,
    RefreshCw,
    RotateCcw,
    Trash2,
    Undo2,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useState } from 'react';

import CheckInArchiveController from '@/actions/App/Http/Controllers/Dashboard/CheckInArchiveController';
import CheckInCancelController from '@/actions/App/Http/Controllers/Dashboard/CheckInCancelController';
import CheckInCompleteController from '@/actions/App/Http/Controllers/Dashboard/CheckInCompleteController';
import CheckInDeleteController from '@/actions/App/Http/Controllers/Dashboard/CheckInDeleteController';
import CheckInPdfController from '@/actions/App/Http/Controllers/Dashboard/CheckInPdfController';
import CheckInReinstateController from '@/actions/App/Http/Controllers/Dashboard/CheckInReinstateController';
import CheckInReopenController from '@/actions/App/Http/Controllers/Dashboard/CheckInReopenController';
import CheckInRestoreController from '@/actions/App/Http/Controllers/Dashboard/CheckInRestoreController';
import EditCheckInDialog from '@/components/dashboard/check-in/EditCheckInDialog';
import type {
    CheckInDeletionImpact,
    CheckInFormFields,
    CheckInRecord,
} from '@/components/dashboard/types';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useCan } from '@/hooks/useCan';
import type { Permission } from '@/types/auth';

export type CheckInAction =
    | 'complete'
    | 'sync-erp'
    | 'edit'
    | 'view-pdf'
    | 'print-pdf'
    | 'email-pdf'
    | 'sms-pdf'
    | 'reopen'
    | 'cancel'
    | 'reinstate'
    | 'archive'
    | 'restore'
    | 'delete';

/**
 * The permission behind each action. Presentation only: wired actions are enforced by their
 * routes, and the rest will be once their routes exist.
 */
const ACTION_PERMISSIONS: Record<CheckInAction, Permission> = {
    complete: 'complete check-ins',
    'sync-erp': 'sync check-ins to erp',
    edit: 'edit check-ins',
    'view-pdf': 'view check-in pdf',
    'print-pdf': 'print check-in pdf',
    'email-pdf': 'email check-in pdf',
    'sms-pdf': 'email check-in pdf',
    reopen: 'reopen check-ins',
    cancel: 'cancel check-ins',
    reinstate: 'reinstate check-ins',
    archive: 'archive check-ins',
    restore: 'restore check-ins',
    delete: 'delete check-ins',
};

/**
 * The one place the toolbar's unwired actions run. None of them reach the backend yet; wire each
 * case here once its route exists. Complete, reopen, cancel, reinstate, archive, restore, delete, edit and
 * the PDF view and print are wired in the toolbar itself.
 */
function performAction(
    action: Exclude<
        CheckInAction,
        | 'complete'
        | 'reopen'
        | 'cancel'
        | 'reinstate'
        | 'archive'
        | 'restore'
        | 'delete'
        | 'edit'
        | 'view-pdf'
        | 'print-pdf'
    >,
): void {
    void action;
}

const MENU_CLASSES =
    'dashboard rounded-lg border border-gray-200 bg-white p-1.5 shadow-lg ring-0 dark:border-gray-700/60 dark:bg-gray-800';

const SECONDARY_BUTTON =
    'btn gap-2 border-gray-200 bg-white text-gray-600 hover:border-gray-300 data-[state=open]:border-gray-300 dark:border-gray-700/60 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-gray-600 dark:data-[state=open]:border-gray-600';

type CopyTarget = 'reference' | 'uuid' | 'link';

export default function CheckInToolbar({
    checkIn,
    fields,
    truckColors,
}: {
    checkIn: CheckInRecord;
    fields: CheckInFormFields;
    truckColors: string[];
}) {
    const canPermission = useCan();
    const can = (action: CheckInAction) =>
        canPermission(ACTION_PERMISSIONS[action]);

    const [copied, setCopied] = useState<CopyTarget | null>(null);
    const [cancelOpen, setCancelOpen] = useState(false);
    const [reopenOpen, setReopenOpen] = useState(false);
    const [reinstateOpen, setReinstateOpen] = useState(false);
    const [archiveOpen, setArchiveOpen] = useState(false);
    const [restoreOpen, setRestoreOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [editOpen, setEditOpen] = useState(false);
    const [completing, setCompleting] = useState(false);
    const [reopening, setReopening] = useState(false);
    const [reinstating, setReinstating] = useState(false);
    const [cancelling, setCancelling] = useState(false);
    const [cancelError, setCancelError] = useState<string | null>(null);
    const [archiving, setArchiving] = useState(false);
    const [archiveError, setArchiveError] = useState<string | null>(null);
    const [restoring, setRestoring] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [deleteError, setDeleteError] = useState<string | null>(null);
    const [printing, setPrinting] = useState(false);

    useEffect(() => {
        if (copied === null) {
            return;
        }

        const timeout = window.setTimeout(() => setCopied(null), 1500);

        return () => window.clearTimeout(timeout);
    }, [copied]);

    const copy = (target: CopyTarget) => {
        const text = {
            reference: checkIn.referenceNumber,
            uuid: checkIn.uuid,
            link: window.location.href,
        }[target];

        void navigator.clipboard?.writeText(text).then(() => setCopied(target));
    };

    const complete = () => {
        router.post(
            CheckInCompleteController.url(checkIn.uuid),
            {},
            {
                preserveScroll: true,
                onStart: () => setCompleting(true),
                onFinish: () => setCompleting(false),
            },
        );
    };

    const reopen = () => {
        router.post(
            CheckInReopenController.url(checkIn.uuid),
            {},
            {
                preserveScroll: true,
                onStart: () => setReopening(true),
                onFinish: () => {
                    setReopening(false);
                    setReopenOpen(false);
                },
            },
        );
    };

    const reinstate = () => {
        router.post(
            CheckInReinstateController.url(checkIn.uuid),
            {},
            {
                preserveScroll: true,
                onStart: () => setReinstating(true),
                onFinish: () => {
                    setReinstating(false);
                    setReinstateOpen(false);
                },
            },
        );
    };

    const cancel = (reason: string) => {
        router.post(
            CheckInCancelController.url(checkIn.uuid),
            { reason },
            {
                preserveScroll: true,
                onStart: () => {
                    setCancelling(true);
                    setCancelError(null);
                },
                onSuccess: () => setCancelOpen(false),
                onError: (errors) =>
                    setCancelError(errors.reason ?? errors.status ?? null),
                onFinish: () => setCancelling(false),
            },
        );
    };

    const changeCancelOpen = (open: boolean) => {
        setCancelOpen(open);

        if (!open) {
            setCancelError(null);
        }
    };

    const archive = (reason: string) => {
        router.post(
            CheckInArchiveController.url(checkIn.uuid),
            { reason: reason === '' ? null : reason },
            {
                preserveScroll: true,
                onStart: () => {
                    setArchiving(true);
                    setArchiveError(null);
                },
                onSuccess: () => setArchiveOpen(false),
                onError: (errors) =>
                    setArchiveError(errors.reason ?? errors.status ?? null),
                onFinish: () => setArchiving(false),
            },
        );
    };

    const changeArchiveOpen = (open: boolean) => {
        setArchiveOpen(open);

        if (!open) {
            setArchiveError(null);
        }
    };

    const destroy = (reason: string) => {
        router.delete(CheckInDeleteController.url(checkIn.uuid), {
            data: { reason },
            onStart: () => {
                setDeleting(true);
                setDeleteError(null);
            },
            onError: (errors) => setDeleteError(errors.reason ?? null),
            onFinish: () => setDeleting(false),
        });
    };

    const changeDeleteOpen = (open: boolean) => {
        setDeleteOpen(open);

        if (!open) {
            setDeleteError(null);
        }
    };

    const restore = () => {
        router.post(
            CheckInRestoreController.url(checkIn.uuid),
            {},
            {
                preserveScroll: true,
                onStart: () => setRestoring(true),
                onFinish: () => {
                    setRestoring(false);
                    setRestoreOpen(false);
                },
            },
        );
    };

    const pdfUrl = CheckInPdfController.url(checkIn.uuid);

    /**
     * Prints without leaving the page: the PDF loads into a hidden same-origin frame and the
     * browser's print dialog opens on it. Where a frame cannot print (some mobile browsers), the
     * PDF opens in a new tab instead, whose viewer offers Print.
     */
    const printPdf = () => {
        setPrinting(true);

        const frame = document.createElement('iframe');
        frame.style.position = 'fixed';
        frame.style.width = '0';
        frame.style.height = '0';
        frame.style.border = '0';
        frame.src = pdfUrl;

        frame.onload = () => {
            setPrinting(false);

            try {
                frame.contentWindow?.focus();
                frame.contentWindow?.print();
            } catch {
                window.open(pdfUrl, '_blank', 'noopener');
            }

            // Removing the frame while the dialog is open cancels the print in some browsers.
            window.setTimeout(() => frame.remove(), 60_000);
        };

        document.body.appendChild(frame);
    };

    const pending = checkIn.status === 'pending';
    const archived = checkIn.archivedAt !== null;

    const showComplete = pending && !archived && can('complete');
    const showSync = checkIn.erpStatus !== 'synced' && can('sync-erp');
    const showReopen =
        checkIn.status === 'completed' && !archived && can('reopen');
    const showCancel = pending && !archived && can('cancel');
    const showReinstate =
        checkIn.status === 'cancelled' && !archived && can('reinstate');
    // Only a completed or canceled check-in may be archived; the server refuses a pending one.
    const showArchive = archived
        ? can('restore')
        : (checkIn.status === 'completed' || checkIn.status === 'cancelled') &&
          can('archive');
    const showEdit = !archived && can('edit');
    const showPdfMenu =
        can('view-pdf') ||
        can('print-pdf') ||
        can('email-pdf') ||
        can('sms-pdf');

    return (
        <div className="flex flex-wrap items-center gap-2">
            {showComplete && (
                <button
                    type="button"
                    onClick={complete}
                    disabled={completing}
                    className="btn gap-2 bg-brand-green text-white hover:bg-brand-green/90 disabled:opacity-50"
                >
                    <CheckCircle2 className="size-4" />
                    Complete
                </button>
            )}

            {showSync && (
                <button
                    type="button"
                    onClick={() => performAction('sync-erp')}
                    className={SECONDARY_BUTTON}
                >
                    <RefreshCw className="size-4 text-sky-500" />
                    {checkIn.erpStatus === 'failed'
                        ? 'Retry ERP sync'
                        : 'Sync to ERP'}
                </button>
            )}

            {showEdit && (
                <button
                    type="button"
                    onClick={() => setEditOpen(true)}
                    className={SECONDARY_BUTTON}
                >
                    <Pencil className="size-4 text-gray-400 dark:text-gray-500" />
                    Edit
                </button>
            )}

            {showPdfMenu && (
                <DropdownMenu modal={false}>
                    <DropdownMenuTrigger asChild>
                        <button type="button" className={SECONDARY_BUTTON}>
                            <FileText className="size-4 text-rose-500" />
                            PDF
                            <ChevronDown className="size-3.5 text-gray-400 dark:text-gray-500" />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        align="end"
                        className={`w-56 ${MENU_CLASSES}`}
                    >
                        {can('view-pdf') && (
                            <DropdownMenuItem className="rounded-md" asChild>
                                {/* A real navigation, not an Inertia visit: Inertia cannot handle
                                    a binary response. */}
                                <a href={pdfUrl} target="_blank" rel="noopener">
                                    <Eye />
                                    View PDF
                                </a>
                            </DropdownMenuItem>
                        )}
                        {can('print-pdf') && (
                            <DropdownMenuItem
                                className="rounded-md"
                                disabled={printing}
                                onSelect={printPdf}
                            >
                                <Printer />
                                {printing ? 'Preparing…' : 'Print'}
                            </DropdownMenuItem>
                        )}
                        {can('email-pdf') && (
                            <DropdownMenuItem
                                className="items-start rounded-md"
                                disabled={checkIn.driver.email === null}
                                onSelect={() => performAction('email-pdf')}
                            >
                                <Mail className="mt-0.5" />
                                <span>
                                    <span className="block">
                                        Email to driver
                                    </span>
                                    {checkIn.driver.email === null && (
                                        <span className="block text-xs text-gray-500 dark:text-gray-400">
                                            No email on file
                                        </span>
                                    )}
                                </span>
                            </DropdownMenuItem>
                        )}
                        {can('sms-pdf') && (
                            <DropdownMenuItem
                                className="items-start rounded-md"
                                onSelect={() => performAction('sms-pdf')}
                            >
                                <MessageSquare className="mt-0.5" />
                                <span>
                                    <span className="block">
                                        Text to driver
                                    </span>
                                    <span className="block text-xs text-gray-500 dark:text-gray-400">
                                        {checkIn.driver.cellphone}
                                    </span>
                                </span>
                            </DropdownMenuItem>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            )}

            <DropdownMenu modal={false}>
                <DropdownMenuTrigger asChild>
                    <button
                        type="button"
                        aria-label="More actions"
                        className={SECONDARY_BUTTON}
                    >
                        <MoreHorizontal className="size-4" />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    align="end"
                    className={`w-60 ${MENU_CLASSES}`}
                >
                    <DropdownMenuLabel className="text-xs font-semibold text-gray-400 uppercase dark:text-gray-500">
                        Copy
                    </DropdownMenuLabel>
                    <CopyItem
                        icon={<Copy />}
                        label="Reference number"
                        copied={copied === 'reference'}
                        onCopy={() => copy('reference')}
                    />
                    <CopyItem
                        icon={<Fingerprint />}
                        label="UUID"
                        copied={copied === 'uuid'}
                        onCopy={() => copy('uuid')}
                    />
                    <CopyItem
                        icon={<Link2 />}
                        label="Link to this page"
                        copied={copied === 'link'}
                        onCopy={() => copy('link')}
                    />

                    {(showReopen ||
                        showCancel ||
                        showReinstate ||
                        showArchive) && <DropdownMenuSeparator />}

                    {showReopen && (
                        <DropdownMenuItem
                            className="rounded-md"
                            onSelect={() => setReopenOpen(true)}
                        >
                            <RotateCcw />
                            Reopen…
                        </DropdownMenuItem>
                    )}

                    {showCancel && (
                        <DropdownMenuItem
                            className="rounded-md"
                            onSelect={() => setCancelOpen(true)}
                        >
                            <Ban />
                            Cancel check-in…
                        </DropdownMenuItem>
                    )}

                    {showReinstate && (
                        <DropdownMenuItem
                            className="rounded-md"
                            onSelect={() => setReinstateOpen(true)}
                        >
                            <Undo2 />
                            Reinstate…
                        </DropdownMenuItem>
                    )}

                    {showArchive && (
                        <DropdownMenuItem
                            className="rounded-md"
                            onSelect={() =>
                                archived
                                    ? setRestoreOpen(true)
                                    : setArchiveOpen(true)
                            }
                        >
                            {archived ? <ArchiveRestore /> : <Archive />}
                            {archived ? 'Restore…' : 'Archive…'}
                        </DropdownMenuItem>
                    )}

                    {can('delete') && (
                        <>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                variant="destructive"
                                className="rounded-md"
                                onSelect={() => setDeleteOpen(true)}
                            >
                                <Trash2 />
                                Delete permanently…
                            </DropdownMenuItem>
                        </>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>

            {showEdit && (
                <EditCheckInDialog
                    open={editOpen}
                    onOpenChange={setEditOpen}
                    checkIn={checkIn}
                    fields={fields}
                    truckColors={truckColors}
                />
            )}
            <StatusConfirmDialog
                open={reopenOpen}
                onOpenChange={setReopenOpen}
                title={`Reopen check-in ${checkIn.referenceNumber}?`}
                description="The check-in moves back to pending and its completion is cleared. This is recorded in the check-in's history."
                keepLabel="Keep completed"
                confirmLabel="Reopen check-in"
                busy={reopening}
                onConfirm={reopen}
            />
            <StatusConfirmDialog
                open={reinstateOpen}
                onOpenChange={setReinstateOpen}
                title={`Reinstate check-in ${checkIn.referenceNumber}?`}
                description="The check-in moves back to pending and its cancellation is cleared. The cancellation reason stays in the check-in's history."
                keepLabel="Keep canceled"
                confirmLabel="Reinstate check-in"
                busy={reinstating}
                onConfirm={reinstate}
            />
            <StatusConfirmDialog
                open={restoreOpen}
                onOpenChange={setRestoreOpen}
                title={`Restore check-in ${checkIn.referenceNumber}?`}
                description="It returns to the check-ins list and can be worked again. This is recorded in the check-in's history."
                keepLabel="Keep archived"
                confirmLabel="Restore check-in"
                busy={restoring}
                onConfirm={restore}
            />
            <ArchiveDialog
                open={archiveOpen}
                onOpenChange={changeArchiveOpen}
                referenceNumber={checkIn.referenceNumber}
                archiving={archiving}
                error={archiveError}
                onConfirm={archive}
            />
            <CancelDialog
                open={cancelOpen}
                onOpenChange={changeCancelOpen}
                referenceNumber={checkIn.referenceNumber}
                cancelling={cancelling}
                error={cancelError}
                onConfirm={cancel}
            />
            <DeleteDialog
                open={deleteOpen}
                onOpenChange={changeDeleteOpen}
                referenceNumber={checkIn.referenceNumber}
                deleting={deleting}
                error={deleteError}
                onConfirm={destroy}
            />
        </div>
    );
}

function CopyItem({
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

/** Confirms a status change that takes no input, such as reopening or reinstating. */
function StatusConfirmDialog({
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
                        className="btn bg-gray-900 text-gray-100 hover:bg-gray-800 disabled:opacity-50 dark:bg-gray-100 dark:text-gray-800 dark:hover:bg-white"
                    >
                        {confirmLabel}
                    </button>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

function CancelDialog({
    open,
    onOpenChange,
    referenceNumber,
    cancelling,
    error,
    onConfirm,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    referenceNumber: string;
    cancelling: boolean;
    /** The server's refusal, e.g. a missing reason or a check-in no longer pending. */
    error: string | null;
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
                    <AlertDialogTitle>
                        Cancel check-in {referenceNumber}?
                    </AlertDialogTitle>
                    <AlertDialogDescription>
                        The check-in moves to canceled. The driver is not
                        notified. This is recorded in the check-in's history.
                    </AlertDialogDescription>
                </AlertDialogHeader>

                <Field data-invalid={error !== null || undefined}>
                    <FieldLabel htmlFor="cancel_reason">Reason</FieldLabel>
                    <Textarea
                        id="cancel_reason"
                        rows={3}
                        maxLength={500}
                        placeholder="Why is this check-in being canceled?"
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
                            disabled={cancelling}
                            className={SECONDARY_BUTTON}
                        >
                            Keep check-in
                        </button>
                    </AlertDialogCancel>
                    <button
                        type="button"
                        disabled={cancelling || reason.trim() === ''}
                        onClick={() => onConfirm(reason.trim())}
                        className="btn bg-rose-500 text-white hover:bg-rose-600 disabled:opacity-50"
                    >
                        Cancel check-in
                    </button>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

function ArchiveDialog({
    open,
    onOpenChange,
    referenceNumber,
    archiving,
    error,
    onConfirm,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    referenceNumber: string;
    archiving: boolean;
    /** The server's refusal, e.g. a reason too long or a check-in that is still pending. */
    error: string | null;
    /** Called with the trimmed reason, empty when none was given. */
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
                    <AlertDialogTitle>
                        Archive check-in {referenceNumber}?
                    </AlertDialogTitle>
                    <AlertDialogDescription>
                        It leaves the check-ins list and shows only under
                        Archived. It can be restored at any time. This is
                        recorded in the check-in's history.
                    </AlertDialogDescription>
                </AlertDialogHeader>

                <Field data-invalid={error !== null || undefined}>
                    <FieldLabel htmlFor="archive_reason">
                        Reason (optional)
                    </FieldLabel>
                    <Textarea
                        id="archive_reason"
                        rows={3}
                        maxLength={500}
                        placeholder="Why is this check-in being archived?"
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
                            disabled={archiving}
                            className={SECONDARY_BUTTON}
                        >
                            Keep check-in
                        </button>
                    </AlertDialogCancel>
                    <button
                        type="button"
                        disabled={archiving}
                        onClick={() => onConfirm(reason.trim())}
                        className="btn bg-gray-900 text-gray-100 hover:bg-gray-800 disabled:opacity-50 dark:bg-gray-100 dark:text-gray-800 dark:hover:bg-white"
                    >
                        Archive check-in
                    </button>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

function DeleteDialog({
    open,
    onOpenChange,
    referenceNumber,
    deleting,
    error,
    onConfirm,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    referenceNumber: string;
    deleting: boolean;
    /** The server's refusal, e.g. a missing or too long reason. */
    error: string | null;
    /** Called with the trimmed reason. */
    onConfirm: (reason: string) => void;
}) {
    const { deletionImpact } = usePage<{
        deletionImpact?: CheckInDeletionImpact | null;
    }>().props;
    const [reason, setReason] = useState('');
    const [confirmation, setConfirmation] = useState('');

    // The counts are an optional prop: fetched each time the dialog opens, so they are current.
    useEffect(() => {
        if (open) {
            router.reload({ only: ['deletionImpact'] });
        }
    }, [open]);

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
                    <AlertDialogTitle>
                        Permanently delete check-in {referenceNumber}?
                    </AlertDialogTitle>
                    <AlertDialogDescription>
                        This cannot be undone. The check-in and everything
                        attached to it is erased from the database, whatever its
                        status. If it is still pending, the truck drops off the
                        live queue for everyone. To keep the record, archive it
                        instead.
                    </AlertDialogDescription>
                </AlertDialogHeader>

                <div className="space-y-4 text-sm">
                    {deletionImpact ? (
                        <>
                            <ImpactList
                                title="Deleted permanently"
                                tone="danger"
                                items={[
                                    {
                                        label: 'The check-in and all its details: driver, truck, trailer, load and license',
                                        active: true,
                                    },
                                    {
                                        label: `${plural(deletionImpact.historyEntries, 'history entry', 'history entries')}, including the record of every notification sent`,
                                        active:
                                            deletionImpact.historyEntries > 0,
                                    },
                                    {
                                        label: plural(
                                            deletionImpact.notes,
                                            'internal note',
                                            'internal notes',
                                        ),
                                        active: deletionImpact.notes > 0,
                                    },
                                    {
                                        label: plural(
                                            deletionImpact.purchaseOrders,
                                            'purchase order',
                                            'purchase orders',
                                        ),
                                        active:
                                            deletionImpact.purchaseOrders > 0,
                                    },
                                    {
                                        label: `${plural(deletionImpact.staffNotifications, 'staff notification', 'staff notifications')} in dashboard bells`,
                                        active:
                                            deletionImpact.staffNotifications >
                                            0,
                                    },
                                    {
                                        label: deletionImpact.hasShortLink
                                            ? 'The short link texted to the driver, which stops working'
                                            : 'No short link was sent to the driver',
                                        active: deletionImpact.hasShortLink,
                                    },
                                    {
                                        label: `${plural(deletionImpact.pendingClaims, 'pending account claim', 'pending account claims')} for this check-in`,
                                        active:
                                            deletionImpact.pendingClaims > 0,
                                    },
                                ]}
                            />
                            <ImpactList
                                title="Kept"
                                tone="neutral"
                                items={[
                                    {
                                        label:
                                            deletionImpact.appointmentReference ===
                                            null
                                                ? 'No linked appointment'
                                                : `Appointment ${deletionImpact.appointmentReference} stays as it is, no longer linked, and its history notes the deletion`,
                                        active:
                                            deletionImpact.appointmentReference !==
                                            null,
                                    },
                                    {
                                        label: 'Your account history records the deletion, the reference number and your reason',
                                        active: true,
                                    },
                                    {
                                        label: deletionImpact.driverHasAccount
                                            ? "The driver's account keeps working, but this check-in leaves their history"
                                            : 'The driver has no account',
                                        active: deletionImpact.driverHasAccount,
                                    },
                                ]}
                            />
                        </>
                    ) : (
                        <div className="animate-pulse space-y-2" aria-hidden>
                            {Array.from({ length: 6 }, (_, index) => (
                                <div
                                    key={index}
                                    className="h-4 rounded bg-gray-100 dark:bg-gray-700/60"
                                />
                            ))}
                        </div>
                    )}
                </div>

                <Field data-invalid={error !== null || undefined}>
                    <FieldLabel htmlFor="delete_reason">Reason</FieldLabel>
                    <Textarea
                        id="delete_reason"
                        rows={3}
                        maxLength={500}
                        placeholder="Why is this check-in being deleted?"
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
                            Keep check-in
                        </button>
                    </AlertDialogCancel>
                    <button
                        type="button"
                        disabled={!ready}
                        onClick={() => onConfirm(reason.trim())}
                        className="btn bg-rose-500 text-white hover:bg-rose-600 disabled:opacity-50"
                    >
                        Delete permanently
                    </button>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

function plural(count: number, one: string, many: string): string {
    return `${count} ${count === 1 ? one : many}`;
}

/**
 * One section of the delete dialog's impact summary. Items that do not apply to this check-in are
 * muted rather than hidden, so every kind of attached record is always listed.
 */
function ImpactList({
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
