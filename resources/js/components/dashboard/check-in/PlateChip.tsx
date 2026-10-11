import RegionLabel from '@/components/dashboard/RegionLabel';

/**
 * A plate number styled like a physical plate, followed by its issuing region when the location
 * collects (or once collected) one. Pass '' for a region part that should stay hidden.
 */
export default function PlateChip({
    plate,
    state,
    country,
}: {
    plate: string;
    state: string;
    country: string;
}) {
    return (
        <span className="inline-flex flex-wrap items-center gap-2 sm:justify-end">
            <span className="rounded-md border-2 border-gray-300 bg-gray-50 px-2 py-0.5 font-mono text-sm font-bold tracking-wider text-gray-800 uppercase dark:border-gray-600 dark:bg-gray-900/40 dark:text-gray-100">
                {plate}
            </span>
            {(state !== '' || country !== '') && (
                <RegionLabel
                    state={state}
                    country={country}
                    className="text-xs font-normal text-gray-500 dark:text-gray-400"
                />
            )}
        </span>
    );
}
