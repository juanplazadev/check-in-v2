import { router } from '@inertiajs/react';
import { useId, useState } from 'react';

import LocationLoadTypeController from '@/actions/App/Http/Controllers/Dashboard/LocationLoadTypeController';
import { SwitchRow } from '@/components/dashboard/location/DockDialog';
import { SECONDARY_BUTTON } from '@/components/dashboard/RecordActionDialogs';
import type { LocationLoadType } from '@/components/dashboard/types';
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

const PRIMARY_BUTTON =
    'btn bg-gray-900 text-gray-100 hover:bg-gray-800 disabled:opacity-50 dark:bg-gray-100 dark:text-gray-800 dark:hover:bg-white';

/** Mirrors AppointmentBookingSetting::MINUTE_STEP and LocationLoadTypeRequest::MAX_DURATION_MINUTES. */
const DURATION_STEP = 15;
const MAX_DURATION = 480;

const DURATION_OPTIONS = Array.from(
    { length: MAX_DURATION / DURATION_STEP },
    (_, index) => (index + 1) * DURATION_STEP,
);

type Errors = Partial<Record<string, string>>;

/** "45 min", "1 h", "1 h 30 min". */
export function formatDuration(minutes: number): string {
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;

    if (hours === 0) {
        return `${rest} min`;
    }

    return rest === 0 ? `${hours} h` : `${hours} h ${rest} min`;
}

interface LoadTypeDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    locationUuid: string;
    /** The load type being edited; absent when adding one. */
    loadType?: LocationLoadType;
}

/** Adds a load type to a location, or edits one. */
export function LoadTypeDialog({
    open,
    onOpenChange,
    ...body
}: LoadTypeDialogProps) {
    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent className="dashboard">
                {/* Radix unmounts the content on close, so each opening starts from the load type. */}
                <LoadTypeBody {...body} onDone={() => onOpenChange(false)} />
            </AlertDialogContent>
        </AlertDialog>
    );
}

function LoadTypeBody({
    locationUuid,
    loadType,
    onDone,
}: Omit<LoadTypeDialogProps, 'open' | 'onOpenChange'> & {
    onDone: () => void;
}) {
    const fieldId = useId();
    const [name, setName] = useState(loadType?.name ?? '');
    const [direction, setDirection] = useState<'inbound' | 'outbound'>(
        loadType?.direction ?? 'inbound',
    );
    const [duration, setDuration] = useState(loadType?.durationMinutes ?? 60);
    const [isActive, setIsActive] = useState(loadType?.isActive ?? true);
    const [busy, setBusy] = useState(false);
    const [errors, setErrors] = useState<Errors>({});

    const save = () => {
        const route = loadType
            ? LocationLoadTypeController.update({
                  uuid: locationUuid,
                  loadTypeUuid: loadType.id,
              })
            : LocationLoadTypeController.store(locationUuid);

        router.visit(route, {
            data: {
                name: name.trim(),
                direction,
                duration_minutes: duration,
                is_active: isActive,
            },
            preserveScroll: true,
            onStart: () => {
                setBusy(true);
                setErrors({});
            },
            onSuccess: onDone,
            onError: setErrors,
            onFinish: () => setBusy(false),
        });
    };

    return (
        <>
            <AlertDialogHeader>
                <AlertDialogTitle>
                    {loadType ? `Edit ${loadType.name}` : 'Add a load type'}
                </AlertDialogTitle>
                <AlertDialogDescription>
                    Drivers pick a load type when they book. Its duration sets
                    how long the booking holds a dock.
                </AlertDialogDescription>
            </AlertDialogHeader>

            <Field data-invalid={errors.name !== undefined || undefined}>
                <FieldLabel htmlFor={`${fieldId}-name`}>Name</FieldLabel>
                <Input
                    id={`${fieldId}-name`}
                    value={name}
                    maxLength={60}
                    placeholder="Live unload, floor loaded"
                    aria-invalid={errors.name !== undefined}
                    onChange={(event) => setName(event.target.value)}
                />
                {errors.name !== undefined && (
                    <FieldError>{errors.name}</FieldError>
                )}
            </Field>

            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    data-invalid={errors.direction !== undefined || undefined}
                >
                    <FieldLabel htmlFor={`${fieldId}-direction`}>
                        Direction
                    </FieldLabel>
                    <select
                        id={`${fieldId}-direction`}
                        className="form-select w-full"
                        value={direction}
                        onChange={(event) =>
                            setDirection(
                                event.target.value as 'inbound' | 'outbound',
                            )
                        }
                    >
                        <option value="inbound">Inbound</option>
                        <option value="outbound">Outbound</option>
                    </select>
                    {errors.direction !== undefined && (
                        <FieldError>{errors.direction}</FieldError>
                    )}
                </Field>

                <Field
                    data-invalid={
                        errors.duration_minutes !== undefined || undefined
                    }
                >
                    <FieldLabel htmlFor={`${fieldId}-duration`}>
                        Duration
                    </FieldLabel>
                    <select
                        id={`${fieldId}-duration`}
                        className="form-select w-full"
                        value={duration}
                        onChange={(event) =>
                            setDuration(Number(event.target.value))
                        }
                    >
                        {DURATION_OPTIONS.map((minutes) => (
                            <option key={minutes} value={minutes}>
                                {formatDuration(minutes)}
                            </option>
                        ))}
                    </select>
                    {errors.duration_minutes !== undefined && (
                        <FieldError>{errors.duration_minutes}</FieldError>
                    )}
                </Field>
            </div>

            {loadType && loadType.upcomingCount > 0 && (
                <p className="text-sm text-gray-500 dark:text-gray-400">
                    A new duration applies to bookings made from now on. The{' '}
                    {loadType.upcomingCount} upcoming{' '}
                    {loadType.upcomingCount === 1
                        ? 'appointment keeps its'
                        : 'appointments keep their'}{' '}
                    current length.
                </p>
            )}

            <SwitchRow
                id={`${fieldId}-active`}
                label="Active"
                hint="Turn off to stop drivers booking this load. Booked appointments keep it."
                checked={isActive}
                onChange={setIsActive}
            />
            {errors.is_active !== undefined && (
                <p className="text-sm text-rose-500">{errors.is_active}</p>
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
                    disabled={busy || name.trim() === ''}
                    onClick={save}
                    className={PRIMARY_BUTTON}
                >
                    {busy ? 'Saving…' : loadType ? 'Save' : 'Add load type'}
                </button>
            </AlertDialogFooter>
        </>
    );
}
