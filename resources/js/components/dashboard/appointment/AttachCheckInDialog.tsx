import type { FormEvent } from 'react';
import { useState } from 'react';

import { performAction } from '@/components/dashboard/appointment/AppointmentToolbar';
import { SECONDARY_BUTTON } from '@/components/dashboard/RecordActionDialogs';
import type { AppointmentRecord } from '@/components/dashboard/types';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Field, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';

/**
 * Ties a scheduled booking to the check-in the driver submitted without it: the reverse of
 * LinkAppointmentDialog on the check-in page. Suggestions (pending check-ins at the same location
 * around the slot that share the driver's phone or name) need a backend endpoint, so for now the
 * list is always empty and staff attach by the check-in's reference number.
 */
export default function AttachCheckInDialog({
    open,
    onOpenChange,
    appointment,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    appointment: AppointmentRecord;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="dashboard sm:max-w-lg">
                {/* Radix unmounts the content on close, so each opening starts empty. */}
                <AttachCheckInBody
                    appointment={appointment}
                    onAttached={() => onOpenChange(false)}
                />
            </DialogContent>
        </Dialog>
    );
}

function AttachCheckInBody({
    appointment,
    onAttached,
}: {
    appointment: AppointmentRecord;
    onAttached: () => void;
}) {
    const [reference, setReference] = useState('');

    const submitReference = (event: FormEvent) => {
        event.preventDefault();

        if (reference.trim() === '') {
            return;
        }

        performAction('attach-check-in', {
            reference_number: reference.trim(),
        });
        onAttached();
    };

    return (
        <>
            <DialogHeader>
                <DialogTitle>Attach to a check-in</DialogTitle>
                <DialogDescription>
                    Pending check-ins at {appointment.location.name} around this
                    appointment that share the driver's cellphone or name.
                    Attaching marks the appointment as checked in.
                </DialogDescription>
            </DialogHeader>

            <p className="rounded-lg border border-dashed border-gray-200 px-4 py-5 text-center text-sm text-gray-500 dark:border-gray-700/60 dark:text-gray-400">
                No pending check-ins at {appointment.location.name} within a day
                of this appointment share the driver's cellphone or name. If the
                driver already checked in, enter the check-in reference below.
            </p>

            <form
                onSubmit={submitReference}
                className="border-t border-gray-100 pt-4 dark:border-gray-700/60"
            >
                <Field>
                    <FieldLabel htmlFor="attach_reference_number">
                        Not listed? Enter the check-in reference
                    </FieldLabel>
                    <div className="flex gap-2">
                        <Input
                            id="attach_reference_number"
                            value={reference}
                            maxLength={20}
                            autoComplete="off"
                            placeholder="e.g. A1B2C3D4"
                            className="font-mono uppercase"
                            onChange={(event) =>
                                setReference(event.target.value)
                            }
                        />
                        <button
                            type="submit"
                            disabled={reference.trim() === ''}
                            className={SECONDARY_BUTTON}
                        >
                            Attach
                        </button>
                    </div>
                </Field>
            </form>
        </>
    );
}
