import { router } from '@inertiajs/react';
import {
    CheckCircle2,
    ChevronDown,
    ChevronRight,
    RotateCcw,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';

import FailedJobController from '@/actions/App/Http/Controllers/Dashboard/FailedJobController';
import Card from '@/components/dashboard/Card';
import type { FailedJob } from '@/components/dashboard/jobs/types';
import {
    SECONDARY_BUTTON,
    StatusConfirmDialog,
} from '@/components/dashboard/RecordActionDialogs';
import { formatDateTime, formatTimeAgo } from '@/lib/dashboard/format';

const ROW_BUTTON =
    'inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 disabled:opacity-50 dark:text-gray-400 dark:hover:bg-gray-700/60 dark:hover:text-gray-200';

/** The class name without its namespace, as most jobs are App\Jobs\Something. */
function shortName(name: string): string {
    return name.split('\\').pop() ?? name;
}

function FailedJobRow({
    job,
    canManage,
    busy,
    onRetry,
    onDelete,
}: {
    job: FailedJob;
    canManage: boolean;
    busy: boolean;
    onRetry: () => void;
    onDelete: () => void;
}) {
    const [open, setOpen] = useState(false);

    return (
        <li className="px-5 py-3">
            <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <button
                    type="button"
                    onClick={() => setOpen(!open)}
                    aria-expanded={open}
                    className="flex min-w-0 items-start gap-2 text-left"
                >
                    {open ? (
                        <ChevronDown className="mt-0.5 size-4 shrink-0 text-gray-400" />
                    ) : (
                        <ChevronRight className="mt-0.5 size-4 shrink-0 text-gray-400" />
                    )}
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <span
                                className="font-semibold text-gray-800 dark:text-gray-100"
                                title={job.name}
                            >
                                {shortName(job.name)}
                            </span>
                            <span className="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-600 dark:bg-gray-700/60 dark:text-gray-300">
                                {job.queue}
                            </span>
                            {job.retried && (
                                <span className="inline-flex items-center gap-1 rounded-full bg-brand-green/12 px-2 py-0.5 text-[11px] font-semibold text-brand-green">
                                    <CheckCircle2 className="size-3" />
                                    Retried
                                </span>
                            )}
                        </div>
                        <p className="mt-1 line-clamp-2 text-xs break-all text-rose-600 dark:text-rose-400">
                            {job.summary}
                        </p>
                        {job.failedAt && (
                            <p
                                className="mt-1 text-xs text-gray-400 dark:text-gray-500"
                                title={formatDateTime(job.failedAt)}
                            >
                                Failed {formatTimeAgo(job.failedAt)}
                            </p>
                        )}
                    </div>
                </button>

                {canManage && (
                    <div className="flex shrink-0 gap-1 pl-6 sm:pl-0">
                        <button
                            type="button"
                            disabled={busy}
                            onClick={onRetry}
                            className={ROW_BUTTON}
                        >
                            <RotateCcw className="size-3.5" />
                            Retry
                        </button>
                        <button
                            type="button"
                            disabled={busy}
                            onClick={onDelete}
                            className={`${ROW_BUTTON} hover:text-rose-600 dark:hover:text-rose-400`}
                        >
                            <Trash2 className="size-3.5" />
                            Delete
                        </button>
                    </div>
                )}
            </div>

            {open && (
                <pre className="mt-3 max-h-80 overflow-auto rounded-lg bg-gray-50 p-3 font-mono text-[11px] leading-relaxed whitespace-pre-wrap text-gray-600 dark:bg-gray-900/40 dark:text-gray-300">
                    {job.exception}
                </pre>
            )}
        </li>
    );
}

/**
 * The failed jobs Horizon still keeps (the latest 50), each expandable to
 * its exception, with retry and delete for those who may manage jobs.
 */
export default function FailedJobsCard({
    jobs,
    total,
    keptMinutes,
    canManage,
}: {
    jobs: FailedJob[];
    total: number;
    keptMinutes: number;
    canManage: boolean;
}) {
    const [busy, setBusy] = useState(false);
    const [deleting, setDeleting] = useState<FailedJob | null>(null);
    const [retryingAll, setRetryingAll] = useState(false);
    const unretried = jobs.filter((job) => !job.retried).length;

    const options = (onDone?: () => void) => ({
        preserveScroll: true,
        onStart: () => setBusy(true),
        onFinish: () => {
            setBusy(false);
            onDone?.();
        },
    });

    return (
        <Card
            className="col-span-full"
            title={
                <>
                    Failed jobs{' '}
                    <span className="font-medium text-gray-400 dark:text-gray-500">
                        {total}
                    </span>
                </>
            }
            action={
                canManage && unretried > 0 ? (
                    <button
                        type="button"
                        disabled={busy}
                        onClick={() => setRetryingAll(true)}
                        className={`${SECONDARY_BUTTON} disabled:opacity-50`}
                    >
                        <RotateCcw className="size-4" />
                        Retry all
                    </button>
                ) : undefined
            }
        >
            {jobs.length === 0 ? (
                <div className="flex flex-col items-center px-5 py-10 text-center">
                    <CheckCircle2 className="mb-2 size-6 text-brand-green" />
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        No failed jobs in the last{' '}
                        {Math.round(keptMinutes / 1440)} days.
                    </p>
                </div>
            ) : (
                <ul className="divide-y divide-gray-100 text-sm dark:divide-gray-700/60">
                    {jobs.map((job) => (
                        <FailedJobRow
                            key={job.id}
                            job={job}
                            canManage={canManage}
                            busy={busy}
                            onRetry={() =>
                                router.post(
                                    FailedJobController.retry.url(job.id),
                                    {},
                                    options(),
                                )
                            }
                            onDelete={() => setDeleting(job)}
                        />
                    ))}
                </ul>
            )}

            {deleting && (
                <StatusConfirmDialog
                    open
                    onOpenChange={(open) => {
                        if (!open && !busy) {
                            setDeleting(null);
                        }
                    }}
                    title="Delete this failed job?"
                    description={`${shortName(deleting.name)} is removed from the failed list for good and can no longer be retried.`}
                    keepLabel="Keep it"
                    confirmLabel="Delete job"
                    busy={busy}
                    onConfirm={() =>
                        router.delete(
                            FailedJobController.destroy.url(deleting.id),
                            options(() => setDeleting(null)),
                        )
                    }
                />
            )}

            <StatusConfirmDialog
                open={retryingAll}
                onOpenChange={(open) => !busy && setRetryingAll(open)}
                title="Retry every failed job?"
                description="Each failed job that has not been retried yet is queued again. Jobs that failed for a lasting reason will most likely fail again."
                keepLabel="Cancel"
                confirmLabel="Retry all"
                busy={busy}
                onConfirm={() =>
                    router.post(
                        FailedJobController.retryAll.url(),
                        {},
                        options(() => setRetryingAll(false)),
                    )
                }
            />
        </Card>
    );
}
