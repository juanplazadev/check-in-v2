import { router } from '@inertiajs/react';
import { MessageSquare, Warehouse } from 'lucide-react';
import { useState } from 'react';

import CheckInDockController from '@/actions/App/Http/Controllers/Dashboard/CheckInDockController';
import Card from '@/components/dashboard/Card';
import AssignDoorDialog from '@/components/dashboard/check-in/AssignDoorDialog';
import type {
    CheckInDoorOption,
    CheckInRecord,
} from '@/components/dashboard/types';
import { formatTime } from '@/lib/dashboard/format';

const SMALL_BUTTON =
    'btn-sm border-gray-200 text-gray-700 hover:border-gray-300 disabled:opacity-50 dark:border-gray-700/60 dark:text-gray-200 dark:hover:border-gray-600';

/**
 * Which door the truck is at, when the driver was last texted it, and, for
 * staff who may assign doors while the truck waits, the actions to change,
 * remove or resend it.
 */
export default function DoorCard({
    checkIn,
    options,
}: {
    checkIn: CheckInRecord;
    /** Null when the viewer may not assign doors, or the check-in is no longer waiting. */
    options: CheckInDoorOption[] | null;
}) {
    const [assigning, setAssigning] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    // Nothing to show on a record that never had a door and can no longer get one.
    if (checkIn.dock === null && options === null) {
        return null;
    }

    const visit = (route: Parameters<typeof router.visit>[0]) =>
        router.visit(route, {
            preserveScroll: true,
            onStart: () => {
                setBusy(true);
                setError(null);
            },
            onError: (errors) =>
                setError(errors.dock ?? 'Something went wrong. Try again.'),
            onFinish: () => setBusy(false),
        });

    return (
        <Card
            title="Door"
            action={
                options !== null && (
                    <button
                        type="button"
                        onClick={() => setAssigning(true)}
                        className={SMALL_BUTTON}
                    >
                        {checkIn.dock ? 'Change door' : 'Assign door'}
                    </button>
                )
            }
        >
            <div className="flex flex-wrap items-center gap-3 px-5 py-4 text-sm">
                <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-sky-50 text-sky-600 dark:bg-sky-500/15 dark:text-sky-300">
                    <Warehouse className="size-4" aria-hidden />
                </span>
                <div className="min-w-0 flex-1">
                    <div className="font-semibold text-gray-800 dark:text-gray-100">
                        {checkIn.dock?.label ?? 'No door yet'}
                    </div>
                    <div className="text-gray-500 dark:text-gray-400">
                        {checkIn.doorNotifiedAt
                            ? `Driver texted at ${formatTime(checkIn.doorNotifiedAt)}`
                            : checkIn.dock
                              ? 'Driver not texted'
                              : 'Send the truck to a door when one is free.'}
                    </div>
                </div>
                {options !== null && checkIn.dock !== null && (
                    <div className="flex gap-2">
                        <button
                            type="button"
                            disabled={busy}
                            onClick={() =>
                                visit(
                                    CheckInDockController.notify(checkIn.uuid),
                                )
                            }
                            className={`${SMALL_BUTTON} gap-1.5`}
                        >
                            <MessageSquare className="size-3.5" aria-hidden />
                            {checkIn.doorNotifiedAt
                                ? 'Resend text'
                                : 'Text driver'}
                        </button>
                        <button
                            type="button"
                            disabled={busy}
                            onClick={() =>
                                visit(
                                    CheckInDockController.destroy(checkIn.uuid),
                                )
                            }
                            className={SMALL_BUTTON}
                        >
                            Remove
                        </button>
                    </div>
                )}
            </div>
            {error !== null && (
                <p className="px-5 pb-4 text-sm text-rose-500">{error}</p>
            )}

            {options !== null && (
                <AssignDoorDialog
                    open={assigning}
                    onOpenChange={setAssigning}
                    checkIn={checkIn}
                    options={options}
                />
            )}
        </Card>
    );
}
