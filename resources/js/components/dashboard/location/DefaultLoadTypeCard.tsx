import { router } from '@inertiajs/react';
import { useId, useState } from 'react';

import LocationLoadTypeController from '@/actions/App/Http/Controllers/Dashboard/LocationLoadTypeController';
import { formatDuration } from '@/components/dashboard/location/LoadTypeDialog';
import type { LocationLoadType } from '@/components/dashboard/types';

const PRIMARY_BUTTON =
    'btn bg-gray-900 text-gray-100 hover:bg-gray-800 disabled:opacity-50 dark:bg-gray-100 dark:text-gray-800 dark:hover:bg-white';

const DRIVERS = 'drivers';
const STAFF = 'staff';

/**
 * Who picks the load type at a location: drivers when they book, or staff,
 * who set the one every booking gets so drivers never see the step. Viewers
 * who may not manage load types see the current choice as text.
 */
export default function DefaultLoadTypeCard({
    locationUuid,
    loadTypes,
    defaultLoadTypeId,
    canManage,
}: {
    locationUuid: string;
    /** Only active ones can be the default. */
    loadTypes: LocationLoadType[];
    /** Null when drivers choose. */
    defaultLoadTypeId: string | null;
    canManage: boolean;
}) {
    const fieldId = useId();
    const [picker, setPicker] = useState(
        defaultLoadTypeId === null ? DRIVERS : STAFF,
    );
    const [loadType, setLoadType] = useState(
        defaultLoadTypeId ?? loadTypes[0]?.id ?? '',
    );
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const current = loadTypes.find((option) => option.id === defaultLoadTypeId);
    const chosen = picker === STAFF ? loadType : null;
    const changed = chosen !== defaultLoadTypeId;

    if (!canManage) {
        return (
            <div className="mb-6 rounded-lg border border-gray-200 px-4 py-3 text-sm text-gray-600 dark:border-gray-700/60 dark:text-gray-300">
                {current ? (
                    <>
                        Staff set the load type. Every booking is made as{' '}
                        <strong>{current.name}</strong>, and drivers don't see a
                        load type step.
                    </>
                ) : (
                    <>Drivers choose the load type when they book.</>
                )}
            </div>
        );
    }

    const save = () => {
        router.visit(LocationLoadTypeController.updateDefault(locationUuid), {
            data: { load_type: chosen },
            preserveScroll: true,
            onStart: () => {
                setBusy(true);
                setError(null);
            },
            onError: (errors) =>
                setError(
                    errors.load_type ??
                        'Who picks the load type could not be saved.',
                ),
            onFinish: () => setBusy(false),
        });
    };

    return (
        <fieldset className="mb-6 rounded-lg border border-gray-200 px-4 py-4 dark:border-gray-700/60">
            <legend className="px-1 text-sm font-semibold text-gray-800 dark:text-gray-100">
                Who picks the load type
            </legend>

            <div className="space-y-3">
                <label className="flex cursor-pointer items-start gap-3">
                    <input
                        type="radio"
                        name={`${fieldId}-picker`}
                        className="form-radio mt-0.5"
                        checked={picker === DRIVERS}
                        onChange={() => setPicker(DRIVERS)}
                    />
                    <span className="text-sm">
                        <span className="block font-medium text-gray-800 dark:text-gray-100">
                            Drivers choose when they book
                        </span>
                        <span className="block text-gray-500 dark:text-gray-400">
                            The booking form asks for the load type first.
                        </span>
                    </span>
                </label>

                <div>
                    <label className="flex cursor-pointer items-start gap-3">
                        <input
                            type="radio"
                            name={`${fieldId}-picker`}
                            className="form-radio mt-0.5"
                            checked={picker === STAFF}
                            onChange={() => setPicker(STAFF)}
                        />
                        <span className="text-sm">
                            <span className="block font-medium text-gray-800 dark:text-gray-100">
                                Staff set it
                            </span>
                            <span className="block text-gray-500 dark:text-gray-400">
                                Drivers don't see a load type step. Every
                                booking is made as the load type below.
                            </span>
                        </span>
                    </label>
                    <div className="mt-2 pl-7">
                        <label
                            htmlFor={`${fieldId}-default`}
                            className="sr-only"
                        >
                            Default load type
                        </label>
                        <select
                            id={`${fieldId}-default`}
                            className="form-select w-full sm:w-80"
                            value={loadType}
                            disabled={picker !== STAFF}
                            onChange={(event) =>
                                setLoadType(event.target.value)
                            }
                        >
                            {loadTypes.map((option) => (
                                <option key={option.id} value={option.id}>
                                    {option.name} (
                                    {formatDuration(option.durationMinutes)})
                                </option>
                            ))}
                        </select>
                    </div>
                </div>
            </div>

            {error !== null && (
                <p className="mt-3 text-sm text-rose-500">{error}</p>
            )}

            <div className="mt-4 flex justify-end">
                <button
                    type="button"
                    disabled={busy || !changed}
                    onClick={save}
                    className={PRIMARY_BUTTON}
                >
                    {busy ? 'Saving…' : 'Save'}
                </button>
            </div>
        </fieldset>
    );
}
