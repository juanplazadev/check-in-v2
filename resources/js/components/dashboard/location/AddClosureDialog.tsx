import { router } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import { CalendarPlus, TriangleAlert } from 'lucide-react';
import type { FormEvent } from 'react';
import { useId, useState } from 'react';

import LocationClosureController from '@/actions/App/Http/Controllers/Dashboard/LocationClosureController';
import { CLOSURE_FEATURES } from '@/components/dashboard/location/ClosureList';
import {
    plural,
    SECONDARY_BUTTON,
} from '@/components/dashboard/RecordActionDialogs';
import type { LocationClosure } from '@/components/dashboard/types';
import { DatePicker } from '@/components/DatePickerTime';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { FieldError } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';

type ClosureFeature = LocationClosure['appliesTo'][number];

type ClosureErrors = Partial<
    Record<
        | 'date'
        | 'reason'
        | 'is_closed'
        | 'open_time'
        | 'close_time'
        | 'applies_to',
        string
    >
>;

const LABEL =
    'mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200';

interface AddClosureDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    locationUuid: string;
    locationName: string;
    /** Y-m-d, today in the location's timezone; earlier days cannot be picked. */
    today: string;
    /** Y-m-d picked on the calendar, or null when opened from the header button. */
    initialDate: string | null;
    /** The holiday on the picked day, used as the starting reason. */
    initialReason: string | null;
    /** Upcoming closures, so a feature already closed on the picked date can't be chosen again. */
    closures: LocationClosure[];
    /** Booked appointments per Y-m-d, for the warning. */
    bookedAppointments: Record<string, number>;
}

/**
 * Closes a location for a day, or keeps special hours, for check-ins, appointments or both.
 */
export default function AddClosureDialog({
    open,
    onOpenChange,
    ...body
}: AddClosureDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="dashboard sm:max-w-lg">
                {/* Radix unmounts the content on close, so each opening starts from initialDate. */}
                <AddClosureBody {...body} onDone={() => onOpenChange(false)} />
            </DialogContent>
        </Dialog>
    );
}

function AddClosureBody({
    locationUuid,
    locationName,
    today,
    initialDate,
    initialReason,
    closures,
    bookedAppointments,
    onDone,
}: Omit<AddClosureDialogProps, 'open' | 'onOpenChange'> & {
    onDone: () => void;
}) {
    const fieldId = useId();
    const [date, setDate] = useState<Date | undefined>(
        initialDate ? parseISO(initialDate) : undefined,
    );
    const [reason, setReason] = useState(initialReason ?? '');
    const [isClosed, setIsClosed] = useState(true);
    const [openTime, setOpenTime] = useState('09:00');
    const [closeTime, setCloseTime] = useState('13:00');
    const [appliesTo, setAppliesTo] = useState<ClosureFeature[]>([
        'checkIns',
        'appointments',
    ]);
    const [busy, setBusy] = useState(false);
    const [errors, setErrors] = useState<ClosureErrors>({});

    const dateKey = date ? format(date, 'yyyy-MM-dd') : null;
    // A feature holds one closure per date, so one already closed that day can't be picked.
    const taken = closures
        .filter((closure) => closure.date === dateKey)
        .flatMap((closure) => closure.appliesTo);
    const chosen = appliesTo.filter((feature) => !taken.includes(feature));
    const booked =
        dateKey !== null && chosen.includes('appointments')
            ? (bookedAppointments[dateKey] ?? 0)
            : 0;

    const toggleFeature = (feature: ClosureFeature, checked: boolean) => {
        setAppliesTo((current) =>
            checked
                ? [...current, feature]
                : current.filter((selected) => selected !== feature),
        );
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        router.post(
            LocationClosureController.store.url(locationUuid),
            {
                date: dateKey,
                reason: reason.trim(),
                is_closed: isClosed,
                ...(isClosed
                    ? {}
                    : { open_time: openTime, close_time: closeTime }),
                applies_to: chosen,
            },
            {
                preserveScroll: true,
                onStart: () => {
                    setBusy(true);
                    setErrors({});
                },
                onSuccess: onDone,
                onError: (next) => setErrors(next as ClosureErrors),
                onFinish: () => setBusy(false),
            },
        );
    };

    return (
        <form onSubmit={submit} className="contents">
            <DialogHeader>
                <DialogTitle>Add a closure</DialogTitle>
                <DialogDescription>
                    Close {locationName} for a day or keep special hours. This
                    overrides the weekly schedule for that date.
                </DialogDescription>
            </DialogHeader>

            <div className="space-y-4">
                <div>
                    <span className={LABEL}>Date</span>
                    <DatePicker
                        date={date}
                        onDateChange={setDate}
                        minDate={parseISO(today)}
                    />
                    {errors.date !== undefined && (
                        <FieldError className="mt-1.5">
                            {errors.date}
                        </FieldError>
                    )}
                </div>

                <div>
                    <label htmlFor={`${fieldId}-reason`} className={LABEL}>
                        Reason
                    </label>
                    <Input
                        id={`${fieldId}-reason`}
                        value={reason}
                        onChange={(event) => setReason(event.target.value)}
                        placeholder="Thanksgiving Day"
                        maxLength={255}
                        aria-invalid={errors.reason !== undefined}
                    />
                    {errors.reason !== undefined && (
                        <FieldError className="mt-1.5">
                            {errors.reason}
                        </FieldError>
                    )}
                </div>

                <div className="flex items-center justify-between gap-4 rounded-lg border border-gray-200 px-3 py-2.5 dark:border-gray-700/60">
                    <label
                        htmlFor={`${fieldId}-closed`}
                        className="text-sm font-medium text-gray-700 dark:text-gray-200"
                    >
                        Closed all day
                        <span className="block text-xs font-normal text-gray-500 dark:text-gray-400">
                            Turn off to keep special hours instead.
                        </span>
                    </label>
                    <Switch
                        id={`${fieldId}-closed`}
                        checked={isClosed}
                        onCheckedChange={setIsClosed}
                    />
                </div>

                {!isClosed && (
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label
                                htmlFor={`${fieldId}-open`}
                                className={LABEL}
                            >
                                Opens
                            </label>
                            <Input
                                id={`${fieldId}-open`}
                                type="time"
                                value={openTime}
                                onChange={(event) =>
                                    setOpenTime(event.target.value)
                                }
                                aria-invalid={errors.open_time !== undefined}
                            />
                            {errors.open_time !== undefined && (
                                <FieldError className="mt-1.5">
                                    {errors.open_time}
                                </FieldError>
                            )}
                        </div>
                        <div>
                            <label
                                htmlFor={`${fieldId}-close`}
                                className={LABEL}
                            >
                                Closes
                            </label>
                            <Input
                                id={`${fieldId}-close`}
                                type="time"
                                value={closeTime}
                                onChange={(event) =>
                                    setCloseTime(event.target.value)
                                }
                                aria-invalid={errors.close_time !== undefined}
                            />
                            {errors.close_time !== undefined && (
                                <FieldError className="mt-1.5">
                                    {errors.close_time}
                                </FieldError>
                            )}
                        </div>
                    </div>
                )}

                <fieldset>
                    <legend className={LABEL}>Applies to</legend>
                    <div className="flex flex-wrap gap-x-6 gap-y-2">
                        {(
                            Object.keys(CLOSURE_FEATURES) as ClosureFeature[]
                        ).map((feature) => {
                            const { label, icon: Icon } =
                                CLOSURE_FEATURES[feature];
                            const isTaken = taken.includes(feature);

                            return (
                                <label
                                    key={feature}
                                    className={`flex items-center ${isTaken ? 'opacity-60' : ''}`}
                                >
                                    <input
                                        type="checkbox"
                                        className="form-checkbox"
                                        checked={chosen.includes(feature)}
                                        disabled={isTaken}
                                        onChange={(event) =>
                                            toggleFeature(
                                                feature,
                                                event.target.checked,
                                            )
                                        }
                                    />
                                    <Icon
                                        className="ml-2 size-4 text-gray-400 dark:text-gray-500"
                                        aria-hidden
                                    />
                                    <span className="ml-1.5 text-sm font-medium text-gray-600 dark:text-gray-300">
                                        {label}
                                        {isTaken && (
                                            <span className="ml-1 text-xs font-normal text-gray-500 dark:text-gray-400">
                                                (already has a closure)
                                            </span>
                                        )}
                                    </span>
                                </label>
                            );
                        })}
                    </div>
                    {errors.applies_to !== undefined && (
                        <FieldError className="mt-1.5">
                            {errors.applies_to}
                        </FieldError>
                    )}
                </fieldset>

                {booked > 0 && (
                    <p className="flex items-start gap-2 rounded-lg bg-amber-500/12 px-3 py-2.5 text-sm text-amber-700 dark:text-amber-400">
                        <TriangleAlert
                            className="mt-0.5 size-4 shrink-0"
                            aria-hidden
                        />
                        <span>
                            {plural(
                                booked,
                                'appointment is',
                                'appointments are',
                            )}{' '}
                            booked on this day. They stay booked, so reach out
                            to those drivers if they need to move.
                        </span>
                    </p>
                )}
            </div>

            <DialogFooter className="items-center">
                {date && (
                    <p className="mr-auto text-xs text-gray-500 dark:text-gray-400">
                        {format(date, 'EEEE, MMMM d, yyyy')}
                    </p>
                )}
                <button
                    type="button"
                    onClick={onDone}
                    disabled={busy}
                    className={SECONDARY_BUTTON}
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    disabled={busy || chosen.length === 0}
                    className="btn gap-2 bg-gray-900 text-gray-100 hover:bg-gray-800 disabled:opacity-50 dark:bg-gray-100 dark:text-gray-800 dark:hover:bg-white"
                >
                    <CalendarPlus className="size-4" aria-hidden />
                    {busy ? 'Adding…' : 'Add closure'}
                </button>
            </DialogFooter>
        </form>
    );
}
