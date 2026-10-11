import { router } from '@inertiajs/react';
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
    MoreHorizontal,
    Pencil,
    Printer,
    RefreshCw,
    RotateCcw,
    Trash2,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useState } from 'react';

import CheckInCompleteController from '@/actions/App/Http/Controllers/Dashboard/CheckInCompleteController';
import CheckInReopenController from '@/actions/App/Http/Controllers/Dashboard/CheckInReopenController';
import type { CheckInRecord } from '@/components/dashboard/types';
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
import { Field, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { hasAnyRole, useStaffRoles } from '@/hooks/useStaffRoles';
import type { StaffRole } from '@/types/staff';

export type CheckInAction =
    | 'complete'
    | 'sync-erp'
    | 'edit'
    | 'view-pdf'
    | 'print-pdf'
    | 'email-pdf'
    | 'reopen'
    | 'cancel'
    | 'archive'
    | 'restore'
    | 'delete';

/**
 * Which roles see each action. Presentation only: wired actions are enforced by their routes, and
 * the rest will be once their routes exist.
 */
const ACTION_ROLES: Record<CheckInAction, StaffRole[]> = {
    complete: ['admin', 'shipping-manager', 'shipping'],
    'sync-erp': ['admin', 'shipping-manager', 'shipping', 'sales'],
    edit: ['admin', 'shipping-manager'],
    'view-pdf': ['admin', 'shipping-manager'],
    'print-pdf': ['admin', 'shipping-manager'],
    'email-pdf': ['admin', 'shipping-manager'],
    reopen: ['admin', 'shipping-manager'],
    cancel: ['admin', 'shipping-manager', 'shipping'],
    archive: ['admin', 'shipping-manager'],
    restore: ['admin', 'shipping-manager'],
    delete: ['admin'],
};

/**
 * The one place the toolbar's unwired actions run. None of them reach the backend yet; wire each
 * case here once its route exists. Complete and reopen are wired in the toolbar itself.
 */
function performAction(
    action: Exclude<CheckInAction, 'complete' | 'reopen'>,
    payload?: { reason: string },
): void {
    void action;
    void payload;
}

const MENU_CLASSES =
    'dashboard rounded-lg border border-gray-200 bg-white p-1.5 shadow-lg ring-0 dark:border-gray-700/60 dark:bg-gray-800';

const SECONDARY_BUTTON =
    'btn gap-2 border-gray-200 bg-white text-gray-600 hover:border-gray-300 data-[state=open]:border-gray-300 dark:border-gray-700/60 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-gray-600 dark:data-[state=open]:border-gray-600';

type CopyTarget = 'reference' | 'uuid' | 'link';

export default function CheckInToolbar({
    checkIn,
}: {
    checkIn: CheckInRecord;
}) {
    const roles = useStaffRoles();
    const can = (action: CheckInAction) =>
        hasAnyRole(roles, ACTION_ROLES[action]);

    const [copied, setCopied] = useState<CopyTarget | null>(null);
    const [cancelOpen, setCancelOpen] = useState(false);
    const [reopenOpen, setReopenOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [completing, setCompleting] = useState(false);
    const [reopening, setReopening] = useState(false);

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

    const pending = checkIn.status === 'pending';
    const archived = checkIn.archivedAt !== null;

    const showComplete = pending && !archived && can('complete');
    const showSync = checkIn.erpStatus !== 'synced' && can('sync-erp');
    const showReopen =
        checkIn.status === 'completed' && !archived && can('reopen');
    const showCancel = pending && can('cancel');
    const showArchive = can(archived ? 'restore' : 'archive');

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

            {can('edit') && (
                <button
                    type="button"
                    onClick={() => performAction('edit')}
                    className={SECONDARY_BUTTON}
                >
                    <Pencil className="size-4 text-gray-400 dark:text-gray-500" />
                    Edit
                </button>
            )}

            {can('view-pdf') && (
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
                        <DropdownMenuItem
                            className="rounded-md"
                            onSelect={() => performAction('view-pdf')}
                        >
                            <Eye />
                            View PDF
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            className="rounded-md"
                            onSelect={() => performAction('print-pdf')}
                        >
                            <Printer />
                            Print
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            className="items-start rounded-md"
                            disabled={checkIn.driver.email === null}
                            onSelect={() => performAction('email-pdf')}
                        >
                            <Mail className="mt-0.5" />
                            <span>
                                <span className="block">Email to driver</span>
                                {checkIn.driver.email === null && (
                                    <span className="block text-xs text-gray-500 dark:text-gray-400">
                                        No email on file
                                    </span>
                                )}
                            </span>
                        </DropdownMenuItem>
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

                    {(showReopen || showCancel || showArchive) && (
                        <DropdownMenuSeparator />
                    )}

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

                    {showArchive && (
                        <DropdownMenuItem
                            className="rounded-md"
                            onSelect={() =>
                                performAction(archived ? 'restore' : 'archive')
                            }
                        >
                            {archived ? <ArchiveRestore /> : <Archive />}
                            {archived ? 'Restore' : 'Archive'}
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

            <ReopenDialog
                open={reopenOpen}
                onOpenChange={setReopenOpen}
                referenceNumber={checkIn.referenceNumber}
                reopening={reopening}
                onConfirm={reopen}
            />
            <CancelDialog
                open={cancelOpen}
                onOpenChange={setCancelOpen}
                referenceNumber={checkIn.referenceNumber}
            />
            <DeleteDialog
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
                referenceNumber={checkIn.referenceNumber}
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

function ReopenDialog({
    open,
    onOpenChange,
    referenceNumber,
    reopening,
    onConfirm,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    referenceNumber: string;
    reopening: boolean;
    onConfirm: () => void;
}) {
    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent className="dashboard">
                <AlertDialogHeader>
                    <AlertDialogTitle>
                        Reopen check-in {referenceNumber}?
                    </AlertDialogTitle>
                    <AlertDialogDescription>
                        The check-in moves back to pending and its completion is
                        cleared. This is recorded in the check-in's history.
                    </AlertDialogDescription>
                </AlertDialogHeader>

                <AlertDialogFooter>
                    <AlertDialogCancel asChild>
                        <button
                            type="button"
                            disabled={reopening}
                            className={SECONDARY_BUTTON}
                        >
                            Keep completed
                        </button>
                    </AlertDialogCancel>
                    <button
                        type="button"
                        disabled={reopening}
                        onClick={onConfirm}
                        className="btn bg-gray-900 text-gray-100 hover:bg-gray-800 disabled:opacity-50 dark:bg-gray-100 dark:text-gray-800 dark:hover:bg-white"
                    >
                        Reopen check-in
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
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    referenceNumber: string;
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
                        The check-in moves to canceled. You can reopen it later.
                    </AlertDialogDescription>
                </AlertDialogHeader>

                <Field>
                    <FieldLabel htmlFor="cancel_reason">Reason</FieldLabel>
                    <Textarea
                        id="cancel_reason"
                        rows={3}
                        maxLength={500}
                        placeholder="Why is this check-in being canceled?"
                        value={reason}
                        onChange={(event) => setReason(event.target.value)}
                    />
                </Field>

                <AlertDialogFooter>
                    <AlertDialogCancel asChild>
                        <button type="button" className={SECONDARY_BUTTON}>
                            Keep check-in
                        </button>
                    </AlertDialogCancel>
                    <button
                        type="button"
                        disabled={reason.trim() === ''}
                        onClick={() => {
                            performAction('cancel', { reason: reason.trim() });
                            changeOpen(false);
                        }}
                        className="btn bg-rose-500 text-white hover:bg-rose-600 disabled:opacity-50"
                    >
                        Cancel check-in
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
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    referenceNumber: string;
}) {
    const [confirmation, setConfirmation] = useState('');

    const changeOpen = (next: boolean) => {
        onOpenChange(next);

        if (!next) {
            setConfirmation('');
        }
    };

    return (
        <AlertDialog open={open} onOpenChange={changeOpen}>
            <AlertDialogContent className="dashboard">
                <AlertDialogHeader>
                    <AlertDialogTitle>Delete permanently?</AlertDialogTitle>
                    <AlertDialogDescription>
                        This removes the check-in, its purchase orders and its
                        history. It cannot be undone. Archive it instead to keep
                        the record.
                    </AlertDialogDescription>
                </AlertDialogHeader>

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
                        <button type="button" className={SECONDARY_BUTTON}>
                            Keep check-in
                        </button>
                    </AlertDialogCancel>
                    <button
                        type="button"
                        disabled={confirmation.trim() !== referenceNumber}
                        onClick={() => {
                            performAction('delete');
                            changeOpen(false);
                        }}
                        className="btn bg-rose-500 text-white hover:bg-rose-600 disabled:opacity-50"
                    >
                        Delete permanently
                    </button>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
