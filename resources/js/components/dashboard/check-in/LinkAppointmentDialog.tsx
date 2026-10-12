import { router, useHttp } from '@inertiajs/react';
import { CalendarClock, Phone, RotateCcw, UserRound } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useEffectEvent, useState } from 'react';

import CheckInAppointmentController from '@/actions/App/Http/Controllers/Dashboard/CheckInAppointmentController';
import type {
    AppointmentLinkSuggestion,
    CheckInRecord,
} from '@/components/dashboard/types';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { formatDateTime } from '@/lib/dashboard/format';

const SECONDARY_BUTTON =
    'btn gap-2 border-gray-200 bg-white text-gray-600 hover:border-gray-300 dark:border-gray-700/60 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-gray-600';

/** How long the suggestions may take before the dialog gives up and offers a retry. */
const LOAD_TIMEOUT_MS = 10_000;

type LinkPayload = { appointment_uuid: string } | { reference_number: string };

type LoadStatus = 'loading' | 'loaded' | 'failed';

/**
 * Ties a check-in to the booking it fulfils. The server suggests bookings at
 * the same location around the arrival that share the driver's phone or name;
 * nothing is linked until staff pick one. A booking that is not suggested can
 * be linked by the reference number on the driver's confirmation.
 */
export default function LinkAppointmentDialog({
    open,
    onOpenChange,
    checkIn,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    checkIn: CheckInRecord;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="dashboard sm:max-w-lg">
                {/* Radix unmounts the content on close, so each opening starts from a fresh load. */}
                <LinkAppointmentBody
                    checkIn={checkIn}
                    onLinked={() => onOpenChange(false)}
                />
            </DialogContent>
        </Dialog>
    );
}

function LinkAppointmentBody({
    checkIn,
    onLinked,
}: {
    checkIn: CheckInRecord;
    onLinked: () => void;
}) {
    const [status, setStatus] = useState<LoadStatus>('loading');
    const [suggestions, setSuggestions] = useState<AppointmentLinkSuggestion[]>(
        [],
    );
    // Bumped by Try again; each value is one request.
    const [attempt, setAttempt] = useState(0);
    const [reference, setReference] = useState('');
    const [linking, setLinking] = useState<string | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const { get, cancel } = useHttp<
        Record<string, never>,
        { suggestions: AppointmentLinkSuggestion[] }
    >({});

    /** Fetches the suggestions, and returns how to abandon the request. */
    const request = useEffectEvent((): (() => void) => {
        let current = true;

        // A request that never answers would otherwise leave the skeleton up for good.
        const timer = setTimeout(() => {
            current = false;
            cancel();
            setStatus('failed');
        }, LOAD_TIMEOUT_MS);

        get(CheckInAppointmentController.index.url(checkIn.uuid))
            .then((response) => {
                if (current) {
                    setSuggestions(response.suggestions);
                    setStatus('loaded');
                }
            })
            .catch(() => {
                if (current) {
                    setStatus('failed');
                }
            })
            .finally(() => clearTimeout(timer));

        return () => {
            current = false;
            clearTimeout(timer);
            cancel();
        };
    });

    useEffect(() => request(), [attempt]);

    const retry = () => {
        setStatus('loading');
        setAttempt((current) => current + 1);
    };

    const link = (payload: LinkPayload, key: string) => {
        router.post(
            CheckInAppointmentController.store.url(checkIn.uuid),
            payload,
            {
                preserveScroll: true,
                onStart: () => {
                    setLinking(key);
                    setErrors({});
                },
                onSuccess: onLinked,
                onError: (next) => setErrors(next),
                onFinish: () => setLinking(null),
            },
        );
    };

    const submitReference = (event: FormEvent) => {
        event.preventDefault();

        if (linking !== null || reference.trim() === '') {
            return;
        }

        link({ reference_number: reference.trim() }, 'reference');
    };

    const generalError = errors.appointment ?? errors.appointment_uuid;

    return (
        <>
            <DialogHeader>
                <DialogTitle>Link an appointment</DialogTitle>
                <DialogDescription>
                    Bookings at {checkIn.location.name} around this check-in
                    that share the driver's cellphone or name. Linking marks the
                    appointment as checked in.
                </DialogDescription>
            </DialogHeader>

            {generalError !== undefined && (
                <div
                    role="alert"
                    className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600 dark:bg-red-500/10 dark:text-red-400"
                >
                    {generalError}
                </div>
            )}

            <div className="space-y-2">
                {status === 'loading' && (
                    <div role="status" className="space-y-2">
                        <p className="text-xs text-gray-500 dark:text-gray-400">
                            Looking for matching appointments…
                        </p>
                        <div className="h-16 animate-pulse rounded-lg bg-gray-100 dark:bg-gray-700/40" />
                        <div className="h-16 animate-pulse rounded-lg bg-gray-100 dark:bg-gray-700/40" />
                    </div>
                )}

                {status === 'failed' && (
                    <div
                        role="alert"
                        className="flex items-center justify-between gap-3 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600 dark:bg-red-500/10 dark:text-red-400"
                    >
                        <span>
                            Couldn't load suggestions. You can still link by
                            reference number below.
                        </span>
                        <button
                            type="button"
                            onClick={retry}
                            className={SECONDARY_BUTTON}
                        >
                            <RotateCcw className="size-3.5" />
                            Try again
                        </button>
                    </div>
                )}

                {status === 'loaded' && suggestions.length === 0 && (
                    <p className="rounded-lg border border-dashed border-gray-200 px-4 py-5 text-center text-sm text-gray-500 dark:border-gray-700/60 dark:text-gray-400">
                        No scheduled appointments at {checkIn.location.name}{' '}
                        within a day of this check-in share the driver's
                        cellphone or name. If the driver has a confirmation,
                        enter its reference below.
                    </p>
                )}

                {status === 'loaded' &&
                    suggestions.map((suggestion) => (
                        <div
                            key={suggestion.uuid}
                            className="flex items-center justify-between gap-3 rounded-lg border border-gray-200 px-4 py-3 dark:border-gray-700/60"
                        >
                            <div className="min-w-0 space-y-1">
                                <div className="flex items-center gap-2">
                                    <span className="font-mono font-semibold text-gray-800 dark:text-gray-100">
                                        {suggestion.referenceNumber}
                                    </span>
                                    {suggestion.phoneMatch && (
                                        <span className="rounded-full bg-emerald-500/12 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 dark:text-emerald-400">
                                            Strong match
                                        </span>
                                    )}
                                </div>
                                <div className="flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-gray-500 dark:text-gray-400">
                                    <span className="inline-flex items-center gap-1">
                                        <CalendarClock className="size-3" />
                                        {formatDateTime(
                                            suggestion.scheduledFor,
                                        )}
                                    </span>
                                    <span className="inline-flex items-center gap-1">
                                        <UserRound className="size-3" />
                                        {suggestion.driverName}
                                    </span>
                                    <span className="inline-flex items-center gap-1">
                                        <Phone className="size-3" />
                                        •••• {suggestion.cellphoneLastFour}
                                    </span>
                                </div>
                            </div>
                            <button
                                type="button"
                                disabled={linking !== null}
                                onClick={() =>
                                    link(
                                        {
                                            appointment_uuid: suggestion.uuid,
                                        },
                                        suggestion.uuid,
                                    )
                                }
                                className="btn shrink-0 bg-gray-900 text-gray-100 hover:bg-gray-800 disabled:opacity-50 dark:bg-gray-100 dark:text-gray-800 dark:hover:bg-white"
                            >
                                {linking === suggestion.uuid
                                    ? 'Linking…'
                                    : 'Link'}
                            </button>
                        </div>
                    ))}
            </div>

            <form
                onSubmit={submitReference}
                className="border-t border-gray-100 pt-4 dark:border-gray-700/60"
            >
                <Field data-invalid={!!errors.reference_number || undefined}>
                    <FieldLabel htmlFor="link_reference_number">
                        Not listed? Enter the appointment reference
                    </FieldLabel>
                    <div className="flex gap-2">
                        <Input
                            id="link_reference_number"
                            value={reference}
                            maxLength={20}
                            autoComplete="off"
                            placeholder="e.g. A1B2C3D4"
                            className="font-mono uppercase"
                            aria-invalid={
                                !!errors.reference_number || undefined
                            }
                            onChange={(event) =>
                                setReference(event.target.value)
                            }
                        />
                        <button
                            type="submit"
                            disabled={
                                linking !== null || reference.trim() === ''
                            }
                            className={SECONDARY_BUTTON}
                        >
                            {linking === 'reference' ? 'Linking…' : 'Link'}
                        </button>
                    </div>
                    <FieldError>{errors.reference_number}</FieldError>
                </Field>
            </form>
        </>
    );
}
