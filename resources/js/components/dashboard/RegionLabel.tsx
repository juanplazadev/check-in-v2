import { countryName } from '@/lib/countryFlag';
import { stateName } from '@/lib/usStates';

/**
 * A stored state and country as one label: the full state name and a small country code badge,
 * e.g. "Sonora [MX]". The full country is in the tooltip. No flag emoji: Windows renders them as
 * bare letters.
 */
export default function RegionLabel({
    state,
    country,
    prefix,
    className = '',
}: {
    state: string | null;
    country: string | null;
    /** Shown before the state, e.g. the destination city. */
    prefix?: string;
    className?: string;
}) {
    const name = stateName(state, country);
    const code = country?.trim().toUpperCase() ?? '';
    const place = [prefix, name].filter(Boolean).join(', ');

    if (place === '' && code === '') {
        return <span className={className}>-</span>;
    }

    return (
        <span
            className={`inline-flex max-w-full min-w-0 items-center gap-1.5 ${className}`}
            title={[place, code ? countryName(code) : null]
                .filter(Boolean)
                .join(', ')}
        >
            {place !== '' && <span className="truncate">{place}</span>}
            {code !== '' && (
                <span className="shrink-0 rounded bg-gray-100 px-1 py-px font-sans text-[10px] leading-tight font-semibold text-gray-500 dark:bg-gray-700/60 dark:text-gray-400">
                    {code}
                </span>
            )}
        </span>
    );
}
