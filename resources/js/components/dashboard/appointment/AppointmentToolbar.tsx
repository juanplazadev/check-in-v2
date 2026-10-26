import { router, usePage } from '@inertiajs/react';
import {
    Archive,
    ArchiveRestore,
    Ban,
    ChevronDown,
    Copy,
    Eye,
    FileText,
    Fingerprint,
    Link2,
    MessageSquare,
    MoreHorizontal,
    Printer,
    Trash2,
    Undo2,
} from 'lucide-react';
import { useEffect, useState } from 'react';

import AppointmentArchiveController from '@/actions/App/Http/Controllers/Dashboard/AppointmentArchiveController';
import AppointmentCancelController from '@/actions/App/Http/Controllers/Dashboard/AppointmentCancelController';
import AppointmentDeleteController from '@/actions/App/Http/Controllers/Dashboard/AppointmentDeleteController';
import AppointmentPdfController from '@/actions/App/Http/Controllers/Dashboard/AppointmentPdfController';
import AppointmentPdfEmailController from '@/actions/App/Http/Controllers/Dashboard/AppointmentPdfEmailController';
import AppointmentReinstateController from '@/actions/App/Http/Controllers/Dashboard/AppointmentReinstateController';
import AppointmentRestoreController from '@/actions/App/Http/Controllers/Dashboard/AppointmentRestoreController';
import {
    CopyItem,
    DeleteRecordDialog,
    EmailPdfItem,
    ImpactList,
    MENU_CLASSES,
    plural,
    ReasonDialog,
    SECONDARY_BUTTON,
    StatusConfirmDialog,
} from '@/components/dashboard/RecordActionDialogs';
import type {
    AppointmentDeletionImpact,
    AppointmentRecord,
} from '@/components/dashboard/types';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useCan } from '@/hooks/useCan';
import { formatPhone } from '@/lib/dashboard/format';
import { printPdf } from '@/lib/dashboard/printPdf';
import type { Permission } from '@/types/auth';

export type AppointmentAction =
    | 'attach-check-in'
    | 'view-pdf'
    | 'print-pdf'
    | 'email-pdf'
    | 'sms-pdf'
    | 'cancel'
    | 'reinstate'
    | 'archive'
    | 'restore'
    | 'delete';

/**
 * The permission behind each action. Presentation only. Null means no permission exists for it
 * yet, so the action shows to anyone who can open the page; replace each null once the backend
 * adds the permission.
 */
const ACTION_PERMISSIONS: Record<AppointmentAction, Permission | null> = {
    'attach-check-in': 'link check-in appointments',
    'view-pdf': 'view appointment pdf',
    'print-pdf': 'print appointment pdf',
    'email-pdf': 'email appointment pdf',
    'sms-pdf': null,
    cancel: 'cancel appointments',
    reinstate: 'reinstate appointments',
    archive: 'archive appointments',
    restore: 'restore appointments',
    delete: 'delete appointments',
};

/**
 * The one place the toolbar's unwired actions run. None of them reach the backend yet; wire each
 * case here (or in the toolbar) once its route exists. The PDF view, print and email, cancel,
 * reinstate, archive, restore and delete are wired in the toolbar itself.
 */
export function performAction(
    action: Exclude<
        AppointmentAction,
        | 'view-pdf'
        | 'print-pdf'
        | 'email-pdf'
        | 'cancel'
        | 'reinstate'
        | 'archive'
        | 'restore'
        | 'delete'
    >,
    payload: Record<string, string | null> = {},
): void {
    void action;
    void payload;
}

type CopyTarget = 'reference' | 'uuid' | 'link';

export default function AppointmentToolbar({
    appointment,
    onAttach,
}: {
    appointment: AppointmentRecord;
    /** Opens the attach-to-check-in dialog, which the page owns. */
    onAttach: () => void;
}) {
    const canPermission = useCan();
    const can = (action: AppointmentAction) => {
        const permission = ACTION_PERMISSIONS[action];

        return permission === null || canPermission(permission);
    };

    const [copied, setCopied] = useState<CopyTarget | null>(null);
    const [cancelOpen, setCancelOpen] = useState(false);
    const [reinstateOpen, setReinstateOpen] = useState(false);
    const [archiveOpen, setArchiveOpen] = useState(false);
    const [restoreOpen, setRestoreOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [reinstating, setReinstating] = useState(false);
    const [cancelling, setCancelling] = useState(false);
    const [cancelError, setCancelError] = useState<string | null>(null);
    const [archiving, setArchiving] = useState(false);
    const [archiveError, setArchiveError] = useState<string | null>(null);
    const [restoring, setRestoring] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [deleteError, setDeleteError] = useState<string | null>(null);
    const [printing, setPrinting] = useState(false);
    const [emailing, setEmailing] = useState(false);
    const [emailed, setEmailed] = useState(false);
    const [emailError, setEmailError] = useState<string | null>(null);

    useEffect(() => {
        if (copied === null) {
            return;
        }

        const timeout = window.setTimeout(() => setCopied(null), 1500);

        return () => window.clearTimeout(timeout);
    }, [copied]);

    useEffect(() => {
        if (!emailed) {
            return;
        }

        const timeout = window.setTimeout(() => setEmailed(false), 2500);

        return () => window.clearTimeout(timeout);
    }, [emailed]);

    const copy = (target: CopyTarget) => {
        const text = {
            reference: appointment.referenceNumber,
            uuid: appointment.uuid,
            link: window.location.href,
        }[target];

        void navigator.clipboard?.writeText(text).then(() => setCopied(target));
    };

    const reinstate = () => {
        router.post(
            AppointmentReinstateController.url(appointment.uuid),
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
            AppointmentCancelController.url(appointment.uuid),
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
            AppointmentArchiveController.url(appointment.uuid),
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

    const restore = () => {
        router.post(
            AppointmentRestoreController.url(appointment.uuid),
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

    const destroy = (reason: string) => {
        router.delete(AppointmentDeleteController.url(appointment.uuid), {
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

    const pdfUrl = AppointmentPdfController.url(appointment.uuid);

    const print = () => {
        setPrinting(true);
        printPdf(pdfUrl, () => setPrinting(false));
    };

    const emailPdf = () => {
        router.post(
            AppointmentPdfEmailController.url(appointment.uuid),
            {},
            {
                preserveScroll: true,
                onStart: () => {
                    setEmailing(true);
                    setEmailError(null);
                },
                onSuccess: () => setEmailed(true),
                onError: (errors) => setEmailError(errors.email ?? null),
                onFinish: () => setEmailing(false),
            },
        );
    };

    const scheduled = appointment.status === 'scheduled';
    const archived = appointment.archivedAt !== null;

    const showAttach =
        scheduled &&
        appointment.checkIn === null &&
        !archived &&
        can('attach-check-in');
    const showCancel = scheduled && !archived && can('cancel');
    const showReinstate =
        appointment.status === 'cancelled' && !archived && can('reinstate');
    // Only a booking that has run its course may be archived; a scheduled one is canceled first.
    const showArchive = archived
        ? can('restore')
        : !scheduled && can('archive');
    const showPdfMenu =
        can('view-pdf') ||
        can('print-pdf') ||
        can('email-pdf') ||
        can('sms-pdf');

    return (
        <div className="flex flex-wrap items-center gap-2">
            {showAttach && (
                <button
                    type="button"
                    onClick={onAttach}
                    className="btn gap-2 bg-brand-green text-white hover:bg-brand-green/90 disabled:opacity-50"
                >
                    <Link2 className="size-4" />
                    Attach to check-in
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
                                onSelect={print}
                            >
                                <Printer />
                                {printing ? 'Preparing…' : 'Print'}
                            </DropdownMenuItem>
                        )}
                        {can('email-pdf') && (
                            <EmailPdfItem
                                email={appointment.driver.email}
                                emailing={emailing}
                                emailed={emailed}
                                error={emailError}
                                onEmail={emailPdf}
                            />
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
                                        {formatPhone(
                                            appointment.driver.cellphone,
                                        )}
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

                    {(showCancel || showReinstate || showArchive) && (
                        <DropdownMenuSeparator />
                    )}

                    {showCancel && (
                        <DropdownMenuItem
                            className="rounded-md"
                            onSelect={() => setCancelOpen(true)}
                        >
                            <Ban />
                            Cancel appointment…
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

            <ReasonDialog
                open={cancelOpen}
                onOpenChange={changeCancelOpen}
                id="cancel_reason"
                title={`Cancel appointment ${appointment.referenceNumber}?`}
                description="The appointment moves to canceled and its time slot opens up again. The driver is not notified. This is recorded in the appointment's history."
                label="Reason"
                placeholder="Why is this appointment being canceled?"
                required
                keepLabel="Keep appointment"
                confirmLabel="Cancel appointment"
                tone="danger"
                busy={cancelling}
                error={cancelError}
                onConfirm={cancel}
            />
            <StatusConfirmDialog
                open={reinstateOpen}
                onOpenChange={setReinstateOpen}
                title={`Reinstate appointment ${appointment.referenceNumber}?`}
                description="The appointment moves back to scheduled and its cancelation is cleared. The driver is not notified. The cancelation reason stays in the appointment's history."
                keepLabel="Keep canceled"
                confirmLabel="Reinstate appointment"
                busy={reinstating}
                onConfirm={reinstate}
            />
            <ReasonDialog
                open={archiveOpen}
                onOpenChange={changeArchiveOpen}
                id="archive_reason"
                title={`Archive appointment ${appointment.referenceNumber}?`}
                description="It leaves the appointments list and shows only under Archived. It can be restored at any time. This is recorded in the appointment's history."
                label="Reason (optional)"
                placeholder="Why is this appointment being archived?"
                required={false}
                keepLabel="Keep appointment"
                confirmLabel="Archive appointment"
                tone="neutral"
                busy={archiving}
                error={archiveError}
                onConfirm={archive}
            />
            <StatusConfirmDialog
                open={restoreOpen}
                onOpenChange={setRestoreOpen}
                title={`Restore appointment ${appointment.referenceNumber}?`}
                description="It returns to the appointments list. This is recorded in the appointment's history."
                keepLabel="Keep archived"
                confirmLabel="Restore appointment"
                busy={restoring}
                onConfirm={restore}
            />
            <AppointmentDeleteDialog
                open={deleteOpen}
                onOpenChange={changeDeleteOpen}
                referenceNumber={appointment.referenceNumber}
                reminderDue={scheduled}
                deleting={deleting}
                error={deleteError}
                onConfirm={destroy}
            />
        </div>
    );
}

/** The appointment delete confirmation, with counts of what goes fetched each time it opens. */
function AppointmentDeleteDialog({
    open,
    onOpenChange,
    referenceNumber,
    reminderDue,
    deleting,
    error,
    onConfirm,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    referenceNumber: string;
    /** A scheduled booking still has its reminder text to come, which the delete stops. */
    reminderDue: boolean;
    deleting: boolean;
    error: string | null;
    onConfirm: (reason: string) => void;
}) {
    const { deletionImpact } = usePage<{
        deletionImpact?: AppointmentDeletionImpact | null;
    }>().props;

    // The counts are an optional prop: fetched each time the dialog opens, so they are current.
    useEffect(() => {
        if (open) {
            router.reload({ only: ['deletionImpact'] });
        }
    }, [open]);

    return (
        <DeleteRecordDialog
            open={open}
            onOpenChange={onOpenChange}
            referenceNumber={referenceNumber}
            title={`Permanently delete appointment ${referenceNumber}?`}
            description="This cannot be undone. The appointment and everything attached to it is erased from the database, whatever its status. To keep the record, archive it instead."
            placeholder="Why is this appointment being deleted?"
            keepLabel="Keep appointment"
            deleting={deleting}
            error={error}
            onConfirm={onConfirm}
            impact={
                deletionImpact ? (
                    <>
                        <ImpactList
                            title="Deleted permanently"
                            tone="danger"
                            items={[
                                {
                                    label: 'The appointment and all its details: driver and time slot',
                                    active: true,
                                },
                                {
                                    label: `${plural(deletionImpact.historyEntries, 'history entry', 'history entries')}, including the record of every notification sent`,
                                    active: deletionImpact.historyEntries > 0,
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
                                    active: deletionImpact.purchaseOrders > 0,
                                },
                                {
                                    label: `${plural(deletionImpact.staffNotifications, 'staff notification', 'staff notifications')} in dashboard bells`,
                                    active:
                                        deletionImpact.staffNotifications > 0,
                                },
                                {
                                    label: deletionImpact.hasShortLink
                                        ? 'The short link texted to the driver, which stops working'
                                        : 'No short link was sent to the driver',
                                    active: deletionImpact.hasShortLink,
                                },
                                {
                                    label: `${plural(deletionImpact.pendingClaims, 'pending account claim', 'pending account claims')} for this appointment`,
                                    active: deletionImpact.pendingClaims > 0,
                                },
                                {
                                    label: reminderDue
                                        ? 'The reminder text still due to the driver, which is no longer sent'
                                        : 'No reminder is still due',
                                    active: reminderDue,
                                },
                            ]}
                        />
                        <ImpactList
                            title="Kept"
                            tone="neutral"
                            items={[
                                {
                                    label:
                                        deletionImpact.checkInReference === null
                                            ? 'No linked check-in'
                                            : `Check-in ${deletionImpact.checkInReference} stays as it is, no longer linked, and its history notes the deletion`,
                                    active:
                                        deletionImpact.checkInReference !==
                                        null,
                                },
                                {
                                    label: 'Your account history records the deletion, the reference number and your reason',
                                    active: true,
                                },
                                {
                                    label: deletionImpact.driverHasAccount
                                        ? "The driver's account keeps working, but this appointment leaves their history"
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
                )
            }
        />
    );
}
