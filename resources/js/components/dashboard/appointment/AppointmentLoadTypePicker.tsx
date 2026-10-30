import { router } from '@inertiajs/react';
import { useId, useState } from 'react';

import AppointmentLoadTypeController from '@/actions/App/Http/Controllers/Dashboard/AppointmentLoadTypeController';
import { formatDuration } from '@/components/dashboard/location/LoadTypeDialog';
import type {
    AppointmentLoadTypeOption,
    AppointmentRecord,
} from '@/components/dashboard/types';

const NO_LOAD_TYPE = '';

/**
 * The load type row's value on an appointment's booking card. Staff who may
 * change load types pick one from the location's active ones, which changes
 * how long the booking holds its dock. Saves on change.
 */
export default function AppointmentLoadTypePicker({
    appointment,
    options,
}: {
    appointment: AppointmentRecord;
    /** Null when the viewer may not change load types, the booking is not live, or the location has none or no bookable docks. */
    options: AppointmentLoadTypeOption[] | null;
}) {
    const id = useId();
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    if (options === null) {
        return <>{appointment.loadType?.name}</>;
    }

    const current = appointment.loadType;
    // A load type turned off after booking is not offered, but still shows as the current one.
    const currentIsOffered =
        current === null ||
        options.some((option) => option.id === current.uuid);

    const change = (loadTypeUuid: string) => {
        router.visit(AppointmentLoadTypeController.update(appointment.uuid), {
            data: { load_type: loadTypeUuid },
            preserveScroll: true,
            onStart: () => {
                setBusy(true);
                setError(null);
            },
            onError: (errors: Record<string, string>) =>
                setError(
                    errors.load_type ?? 'The load type could not be saved.',
                ),
            onFinish: () => setBusy(false),
        });
    };

    return (
        <span className="flex flex-col items-stretch gap-1 sm:items-end">
            <label htmlFor={id} className="sr-only">
                Load type
            </label>
            <select
                id={id}
                className="form-select w-full sm:w-64"
                value={current?.uuid ?? NO_LOAD_TYPE}
                disabled={busy}
                onChange={(event) => change(event.target.value)}
            >
                {current === null && (
                    <option value={NO_LOAD_TYPE} disabled>
                        No load type
                    </option>
                )}
                {!currentIsOffered && (
                    <option value={current.uuid} disabled>
                        {current.name} (turned off)
                    </option>
                )}
                {options.map((option) => (
                    <option key={option.id} value={option.id}>
                        {option.name} ({formatDuration(option.durationMinutes)})
                    </option>
                ))}
            </select>
            {error !== null && (
                <span className="text-sm text-rose-500">{error}</span>
            )}
        </span>
    );
}
