import { router } from '@inertiajs/react';
import { Pause, Play, RotateCcw } from 'lucide-react';
import { useState } from 'react';

import JobCommandController from '@/actions/App/Http/Controllers/Dashboard/JobCommandController';
import type {
    HorizonCommand,
    HorizonStatus,
} from '@/components/dashboard/jobs/types';
import {
    SECONDARY_BUTTON,
    StatusConfirmDialog,
} from '@/components/dashboard/RecordActionDialogs';

const CONFIRM: Record<
    HorizonCommand,
    {
        title: string;
        description: string;
        confirmLabel: string;
    }
> = {
    restart: {
        title: 'Restart the queue workers?',
        description:
            'Every worker stops after its current job and a fresh one takes its place with the latest code. Use this when jobs are stuck or after a deploy. Queued jobs are kept.',
        confirmLabel: 'Restart workers',
    },
    pause: {
        title: 'Pause the queue workers?',
        description:
            'Workers finish the job they are on, then stop picking up new ones. Jobs keep queuing up (PDFs, exports, emails) until you resume.',
        confirmLabel: 'Pause workers',
    },
    continue: {
        title: 'Resume the queue workers?',
        description:
            'Workers start picking up queued jobs again, oldest first.',
        confirmLabel: 'Resume workers',
    },
};

/**
 * Restart and pause/resume buttons for the jobs page header. Each asks first;
 * Horizon applies the command on its next loop, which the page's polling
 * picks up within a few seconds.
 */
export default function WorkerControls({ status }: { status: HorizonStatus }) {
    const [pending, setPending] = useState<HorizonCommand | null>(null);
    const [busy, setBusy] = useState(false);
    const inactive = status === 'inactive';

    const send = (command: HorizonCommand) => {
        router.post(
            JobCommandController.url(),
            { command },
            {
                preserveScroll: true,
                onStart: () => setBusy(true),
                onFinish: () => {
                    setBusy(false);
                    setPending(null);
                },
            },
        );
    };

    const pauseCommand: HorizonCommand =
        status === 'paused' ? 'continue' : 'pause';

    return (
        <>
            <button
                type="button"
                disabled={inactive}
                onClick={() => setPending(pauseCommand)}
                className={`${SECONDARY_BUTTON} disabled:opacity-50`}
            >
                {pauseCommand === 'continue' ? (
                    <Play className="size-4" />
                ) : (
                    <Pause className="size-4" />
                )}
                {pauseCommand === 'continue' ? 'Resume' : 'Pause'}
            </button>
            <button
                type="button"
                disabled={inactive}
                onClick={() => setPending('restart')}
                className="btn gap-2 bg-gray-900 text-gray-100 hover:bg-gray-800 disabled:opacity-50 dark:bg-gray-100 dark:text-gray-800 dark:hover:bg-white"
            >
                <RotateCcw className="size-4" />
                Restart workers
            </button>

            {pending && (
                <StatusConfirmDialog
                    open
                    onOpenChange={(open) => {
                        if (!open && !busy) {
                            setPending(null);
                        }
                    }}
                    title={CONFIRM[pending].title}
                    description={CONFIRM[pending].description}
                    keepLabel="Cancel"
                    confirmLabel={CONFIRM[pending].confirmLabel}
                    busy={busy}
                    onConfirm={() => send(pending)}
                />
            )}
        </>
    );
}
