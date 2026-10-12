import { DetailHint } from '@/components/dashboard/DetailCard';
import RegionLabel from '@/components/dashboard/RegionLabel';

/**
 * A plate number drawn like a physical plate, with its issuing region in a band across the top
 * when the location collects (or once collected) one. Pass '' for a region part that should stay
 * hidden.
 */
export default function LicensePlate({
    plate,
    state,
    country,
    hint,
}: {
    plate: string;
    state: string;
    country: string;
    /** A note about the region, e.g. that the location no longer collects it. */
    hint?: string;
}) {
    const hasRegion = state !== '' || country !== '';

    return (
        <div className="w-full max-w-60 rounded-lg border-2 border-gray-300 bg-white px-4 py-2.5 text-center shadow-xs dark:border-gray-600 dark:bg-gray-800">
            {hasRegion && (
                <div className="flex items-center justify-center gap-1.5 text-[11px] font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400">
                    <RegionLabel state={state} country={country} />
                    {hint !== undefined && <DetailHint hint={hint} />}
                </div>
            )}
            <div
                className="truncate font-mono text-2xl font-bold tracking-[0.2em] text-gray-800 uppercase dark:text-gray-100"
                title={plate}
            >
                {plate}
            </div>
        </div>
    );
}
