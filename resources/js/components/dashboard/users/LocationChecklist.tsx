import { Check, MapPin, Search } from 'lucide-react';
import { useMemo, useState } from 'react';

import type { NewUserLocation } from '@/components/dashboard/users/types';
import { Input } from '@/components/ui/input';

/**
 * A searchable checkbox list of locations, for the add user dialog. Shaped like StaffPicker so the
 * two pickers read the same.
 */
export default function LocationChecklist({
    locations,
    selected,
    onChange,
    invalid,
}: {
    /** Undefined while loading; shows a skeleton. */
    locations: NewUserLocation[] | undefined;
    selected: string[];
    onChange: (selected: string[]) => void;
    invalid?: boolean;
}) {
    const [search, setSearch] = useState('');

    const filtered = useMemo(() => {
        const term = search.trim().toLowerCase();

        return (locations ?? []).filter(
            (location) =>
                term === '' ||
                location.name.toLowerCase().includes(term) ||
                location.city.toLowerCase().includes(term),
        );
    }, [locations, search]);

    const toggle = (uuid: string) =>
        onChange(
            selected.includes(uuid)
                ? selected.filter((each) => each !== uuid)
                : [...selected, uuid],
        );

    const allSelected =
        locations !== undefined &&
        locations.length > 0 &&
        locations.every((location) => selected.includes(location.uuid));

    return (
        <div className="space-y-2">
            <div className="flex items-center gap-2">
                <div className="relative grow">
                    <Search
                        aria-hidden
                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-gray-400"
                    />
                    <Input
                        type="search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Search by name or city"
                        aria-label="Search locations"
                        className="pl-9"
                    />
                </div>
                {locations !== undefined && locations.length > 1 && (
                    <button
                        type="button"
                        onClick={() =>
                            onChange(
                                allSelected
                                    ? []
                                    : locations.map(
                                          (location) => location.uuid,
                                      ),
                            )
                        }
                        className="shrink-0 cursor-pointer text-sm font-medium text-brand-green hover:opacity-80"
                    >
                        {allSelected ? 'Clear all' : 'Select all'}
                    </button>
                )}
            </div>

            <div
                className={`max-h-56 overflow-y-auto rounded-lg border ${
                    invalid
                        ? 'border-red-300 dark:border-red-500/50'
                        : 'border-gray-200 dark:border-gray-700/60'
                }`}
            >
                {locations === undefined ? (
                    <div role="status" className="space-y-2 p-3">
                        <span className="sr-only">Loading locations…</span>
                        <div className="h-10 animate-pulse rounded-lg bg-gray-100 dark:bg-gray-700/40" />
                        <div className="h-10 animate-pulse rounded-lg bg-gray-100 dark:bg-gray-700/40" />
                        <div className="h-10 animate-pulse rounded-lg bg-gray-100 dark:bg-gray-700/40" />
                    </div>
                ) : filtered.length === 0 ? (
                    <p className="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                        {locations.length === 0
                            ? 'There are no locations yet.'
                            : 'No locations match your search.'}
                    </p>
                ) : (
                    <ul className="divide-y divide-gray-100 dark:divide-gray-700/60">
                        {filtered.map((location) => {
                            const isSelected = selected.includes(location.uuid);

                            return (
                                <li key={location.uuid}>
                                    <button
                                        type="button"
                                        role="checkbox"
                                        aria-checked={isSelected}
                                        onClick={() => toggle(location.uuid)}
                                        className={`flex w-full cursor-pointer items-center gap-3 px-3 py-2 text-left ${
                                            isSelected
                                                ? 'bg-brand-green/[0.06] dark:bg-brand-green/[0.12]'
                                                : 'hover:bg-gray-50 dark:hover:bg-gray-700/30'
                                        }`}
                                    >
                                        <span
                                            className={`flex size-7 shrink-0 items-center justify-center rounded-full ${
                                                isSelected
                                                    ? 'bg-brand-green text-white'
                                                    : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-300'
                                            }`}
                                        >
                                            {isSelected ? (
                                                <Check
                                                    className="size-3.5"
                                                    aria-hidden
                                                />
                                            ) : (
                                                <MapPin
                                                    className="size-3.5"
                                                    aria-hidden
                                                />
                                            )}
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate text-sm font-medium text-gray-800 dark:text-gray-100">
                                                {location.name}
                                            </span>
                                            <span className="block truncate text-xs text-gray-500 dark:text-gray-400">
                                                {location.city}
                                            </span>
                                        </span>
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>
        </div>
    );
}
