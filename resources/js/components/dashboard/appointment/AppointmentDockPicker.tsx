import { router } from '@inertiajs/react';
import { useId, useState } from 'react';

import AppointmentDockController from '@/actions/App/Http/Controllers/Dashboard/AppointmentDockController';
import type {
    AppointmentDockOption,
    AppointmentRecord,
} from '@/components/dashboard/types';

const NO_DOCK = '';

/**
 * The dock row's value on an appointment's booking card. Staff who may assign
 * docks pick one from a list; a dock another booking holds at the same time
 * is listed but disabled. Saves on change.
 */
export default function AppointmentDockPicker({
    appointment,
    options,
}: {
    appointment: AppointmentRecord;
    /** Null when the viewer may not assign docks, or the booking is not live. */
    options: AppointmentDockOption[] | null;
}) {
    const id = useId();
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    if (options === null) {
        return appointment.dock ? (
            <>{appointment.dock.label}</>
        ) : (
            <span className="text-gray-400 dark:text-gray-500">-</span>
        );
    }

    if (options.length === 0) {
        return (
            <span className="text-gray-500 dark:text-gray-400">
                {appointment.dock?.label ?? 'No docks at this location yet'}
            </span>
        );
    }

    const change = (dockUuid: string) => {
        const visitOptions = {
            preserveScroll: true,
            onStart: () => {
                setBusy(true);
                setError(null);
            },
            onError: (errors: Record<string, string>) =>
                setError(errors.dock ?? 'The dock could not be saved.'),
            onFinish: () => setBusy(false),
        };

        if (dockUuid === NO_DOCK) {
            router.visit(
                AppointmentDockController.destroy(appointment.uuid),
                visitOptions,
            );

            return;
        }

        router.visit(AppointmentDockController.update(appointment.uuid), {
            ...visitOptions,
            data: { dock: dockUuid },
        });
    };

    const buildings = [...new Set(options.map((option) => option.building))];

    const optionFor = (option: AppointmentDockOption) => (
        <option
            key={option.id}
            value={option.id}
            disabled={option.takenBy !== null}
        >
            {option.label}
            {option.takenBy !== null ? ` (taken by ${option.takenBy})` : ''}
        </option>
    );

    return (
        <span className="flex flex-col items-stretch gap-1 sm:items-end">
            <label htmlFor={id} className="sr-only">
                Dock
            </label>
            <select
                id={id}
                className="form-select w-full sm:w-64"
                value={appointment.dock?.uuid ?? NO_DOCK}
                disabled={busy}
                onChange={(event) => change(event.target.value)}
            >
                <option value={NO_DOCK}>No dock yet</option>
                {buildings.length > 1 || buildings[0] !== null
                    ? buildings.map((building) => (
                          <optgroup
                              key={building ?? 'none'}
                              label={building ?? 'No building'}
                          >
                              {options
                                  .filter(
                                      (option) => option.building === building,
                                  )
                                  .map(optionFor)}
                          </optgroup>
                      ))
                    : options.map(optionFor)}
            </select>
            {error !== null && (
                <span className="text-sm text-rose-500">{error}</span>
            )}
        </span>
    );
}
