import { router } from '@inertiajs/react';
import { PauseCircle } from 'lucide-react';
import type { ReactNode } from 'react';
import { useId, useState } from 'react';

import { SECONDARY_BUTTON } from '@/components/dashboard/RecordActionDialogs';
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
import { Input } from '@/components/ui/input';

export interface PauseDurationOption<T extends string> {
    value: T;
    label: string;
}

interface PauseDialogProps<T extends string> {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** What is paused, as the title and button read it, e.g. "check-ins". */
    feature: string;
    description: ReactNode;
    placeholder: string;
    /** The first one starts selected. */
    durations: PauseDurationOption<T>[];
    /** Where the reason and duration are posted. */
    url: string;
}

/**
 * Pauses new check-ins or new bookings at a location, such as for a full yard. Asks only for a
 * reason, which drivers or guests see, and how long.
 */
export default function PauseDialog<T extends string>({
    open,
    onOpenChange,
    ...body
}: PauseDialogProps<T>) {
    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent className="dashboard">
                {/* Radix unmounts the content on close, so each opening starts empty. */}
                <PauseBody {...body} onDone={() => onOpenChange(false)} />
            </AlertDialogContent>
        </AlertDialog>
    );
}

function PauseBody<T extends string>({
    feature,
    description,
    placeholder,
    durations,
    url,
    onDone,
}: Omit<PauseDialogProps<T>, 'open' | 'onOpenChange'> & {
    onDone: () => void;
}) {
    const fieldId = useId();
    const [reason, setReason] = useState('');
    const [duration, setDuration] = useState<T | undefined>(
        durations[0]?.value,
    );
    const [busy, setBusy] = useState(false);
    const [errors, setErrors] = useState<{
        reason?: string;
        duration?: string;
    }>({});

    const pause = () => {
        router.post(
            url,
            { reason: reason.trim(), duration: duration ?? null },
            {
                preserveScroll: true,
                onStart: () => {
                    setBusy(true);
                    setErrors({});
                },
                onSuccess: onDone,
                onError: (next) =>
                    setErrors({
                        reason: next.reason,
                        duration: next.duration,
                    }),
                onFinish: () => setBusy(false),
            },
        );
    };

    return (
        <>
            <AlertDialogHeader>
                <AlertDialogTitle>Pause {feature}?</AlertDialogTitle>
                <AlertDialogDescription>{description}</AlertDialogDescription>
            </AlertDialogHeader>

            <Field data-invalid={errors.reason !== undefined || undefined}>
                <FieldLabel htmlFor={`${fieldId}-reason`}>Reason</FieldLabel>
                <Input
                    id={`${fieldId}-reason`}
                    value={reason}
                    maxLength={255}
                    placeholder={placeholder}
                    aria-invalid={errors.reason !== undefined}
                    onChange={(event) => setReason(event.target.value)}
                />
                {errors.reason !== undefined && (
                    <FieldError>{errors.reason}</FieldError>
                )}
            </Field>

            <fieldset>
                <legend className="mb-2 text-sm font-medium text-gray-700 dark:text-gray-200">
                    For how long
                </legend>
                <div className="flex flex-wrap gap-2">
                    {durations.map((option) => (
                        <label key={option.value} className="cursor-pointer">
                            <input
                                type="radio"
                                name={`${fieldId}-duration`}
                                value={option.value}
                                checked={duration === option.value}
                                onChange={() => setDuration(option.value)}
                                className="peer sr-only"
                            />
                            <span className="inline-flex rounded-full border border-gray-200 px-3 py-1 text-sm font-medium text-gray-600 peer-checked:border-gray-900 peer-checked:bg-gray-900 peer-checked:text-gray-100 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-green dark:border-gray-700/60 dark:text-gray-300 dark:peer-checked:border-gray-100 dark:peer-checked:bg-gray-100 dark:peer-checked:text-gray-800">
                                {option.label}
                            </span>
                        </label>
                    ))}
                </div>
                {errors.duration !== undefined && (
                    <p className="mt-2 text-sm text-rose-500">
                        {errors.duration}
                    </p>
                )}
            </fieldset>

            <AlertDialogFooter>
                <AlertDialogCancel asChild>
                    <button
                        type="button"
                        disabled={busy}
                        className={SECONDARY_BUTTON}
                    >
                        Keep open
                    </button>
                </AlertDialogCancel>
                <button
                    type="button"
                    disabled={busy || reason.trim() === ''}
                    onClick={pause}
                    className="btn gap-2 bg-amber-500 text-white hover:bg-amber-600 disabled:opacity-50"
                >
                    <PauseCircle className="size-4" aria-hidden />
                    Pause {feature}
                </button>
            </AlertDialogFooter>
        </>
    );
}
