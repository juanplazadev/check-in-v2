import { router } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { useEffect, useId, useState } from 'react';

import CheckInDockController from '@/actions/App/Http/Controllers/Dashboard/CheckInDockController';
import { SECONDARY_BUTTON } from '@/components/dashboard/RecordActionDialogs';
import type {
    CheckInDoorOption,
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
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { formatPhone } from '@/lib/dashboard/format';

interface AssignDoorDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    checkIn: CheckInRecord | null;
    /** The check-in's location's doors; undefined until loaded. */
    options: CheckInDoorOption[] | undefined;
    /** The optional page prop to partial-reload on open, when options are not passed in up front. */
    reloadProp?: string;
}

/**
 * Sends a waiting truck to a door and, unless unticked, texts the driver
 * where to go. Doors another waiting truck is using are listed but disabled.
 * Shared by the live queue and the check-in page.
 */
export default function AssignDoorDialog({
    open,
    onOpenChange,
    checkIn,
    ...body
}: AssignDoorDialogProps) {
    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent className="dashboard">
                {/* Radix unmounts the content on close, so each opening starts from the check-in. */}
                {checkIn && (
                    <DoorBody
                        {...body}
                        checkIn={checkIn}
                        onDone={() => onOpenChange(false)}
                    />
                )}
            </AlertDialogContent>
        </AlertDialog>
    );
}

function DoorBody({
    checkIn,
    options,
    reloadProp,
    onDone,
}: Omit<AssignDoorDialogProps, 'open' | 'onOpenChange' | 'checkIn'> & {
    checkIn: CheckInRecord;
    onDone: () => void;
}) {
    const fieldId = useId();
    const [loading, setLoading] = useState(reloadProp !== undefined);
    const [dock, setDock] = useState(
        checkIn.dock?.uuid ?? checkIn.appointmentDockUuid ?? '',
    );
    const [notify, setNotify] = useState(true);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    // Run from the mounted body: a parent setting open=true never fires onOpenChange.
    useEffect(() => {
        if (reloadProp !== undefined) {
            router.reload({
                only: [reloadProp],
                onFinish: () => setLoading(false),
            });
        }
    }, [reloadProp]);

    const save = () => {
        router.visit(CheckInDockController.update(checkIn.uuid), {
            data: { dock, notify },
            preserveScroll: true,
            onStart: () => {
                setBusy(true);
                setError(null);
            },
            onSuccess: onDone,
            onError: (errors) =>
                setError(errors.dock ?? 'The door could not be saved.'),
            onFinish: () => setBusy(false),
        });
    };

    const doors = options ?? [];
    const buildings = [...new Set(doors.map((door) => door.building))];
    const grouped = buildings.length > 1 || buildings[0] != null;
    const isCurrent = (door: CheckInDoorOption) =>
        door.inUseBy === checkIn.referenceNumber;

    const optionFor = (door: CheckInDoorOption) => {
        const taken = door.inUseBy !== null && !isCurrent(door);

        return (
            <option key={door.id} value={door.id} disabled={taken}>
                {door.label}
                {taken ? ` (in use by ${door.inUseBy})` : ''}
                {door.id === checkIn.appointmentDockUuid &&
                !isCurrent(door) &&
                !taken
                    ? ' (booked door)'
                    : ''}
            </option>
        );
    };

    return (
        <>
            <AlertDialogHeader>
                <AlertDialogTitle>
                    {checkIn.dock ? 'Change door' : 'Assign a door'} for{' '}
                    {checkIn.referenceNumber}
                </AlertDialogTitle>
                <AlertDialogDescription>
                    {checkIn.driver.name} at {checkIn.location.name}.
                </AlertDialogDescription>
            </AlertDialogHeader>

            {loading ? (
                <div className="flex items-center gap-2 py-4 text-sm text-gray-500 dark:text-gray-400">
                    <LoaderCircle className="size-4 animate-spin" aria-hidden />
                    Loading doors…
                </div>
            ) : doors.length === 0 ? (
                <p className="rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-600 dark:bg-gray-900/20 dark:text-gray-300">
                    This location has no active doors yet. Add them on the
                    location's Docks & buildings page.
                </p>
            ) : (
                <>
                    <Field data-invalid={error !== null || undefined}>
                        <FieldLabel htmlFor={`${fieldId}-door`}>
                            Door
                        </FieldLabel>
                        <select
                            id={`${fieldId}-door`}
                            className="form-select w-full"
                            value={dock}
                            onChange={(event) => setDock(event.target.value)}
                        >
                            <option value="" disabled>
                                Choose a door
                            </option>
                            {grouped
                                ? buildings.map((building) => (
                                      <optgroup
                                          key={building ?? 'none'}
                                          label={building ?? 'No building'}
                                      >
                                          {doors
                                              .filter(
                                                  (door) =>
                                                      door.building ===
                                                      building,
                                              )
                                              .map(optionFor)}
                                      </optgroup>
                                  ))
                                : doors.map(optionFor)}
                        </select>
                        {error !== null && <FieldError>{error}</FieldError>}
                    </Field>

                    <label className="flex items-start gap-2 text-sm">
                        <input
                            type="checkbox"
                            className="form-checkbox mt-0.5"
                            checked={notify}
                            onChange={(event) =>
                                setNotify(event.target.checked)
                            }
                        />
                        <span>
                            <span className="block font-medium text-gray-800 dark:text-gray-100">
                                Text the driver
                            </span>
                            <span className="block text-gray-500 dark:text-gray-400">
                                Sends the door to{' '}
                                {formatPhone(checkIn.driver.cellphone)}.
                            </span>
                        </span>
                    </label>
                </>
            )}

            <AlertDialogFooter>
                <AlertDialogCancel asChild>
                    <button
                        type="button"
                        disabled={busy}
                        className={SECONDARY_BUTTON}
                    >
                        Cancel
                    </button>
                </AlertDialogCancel>
                <button
                    type="button"
                    disabled={busy || loading || dock === ''}
                    onClick={save}
                    className="btn bg-gray-900 text-gray-100 hover:bg-gray-800 disabled:opacity-50 dark:bg-gray-100 dark:text-gray-800 dark:hover:bg-white"
                >
                    {busy ? 'Saving…' : notify ? 'Save and text' : 'Save'}
                </button>
            </AlertDialogFooter>
        </>
    );
}
