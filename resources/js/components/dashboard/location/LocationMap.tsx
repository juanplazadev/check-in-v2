import { ExternalLink, MapPinOff } from 'lucide-react';
import type { ReactNode } from 'react';
import { lazy, Suspense, useSyncExternalStore } from 'react';

import type { LocationMapPoint } from '@/components/dashboard/types';

const LeafletMap = lazy(
    () => import('@/components/dashboard/location/LeafletMap'),
);

const subscribe = () => () => {};

/** True only after hydration, so the server render and the first client render match. */
function useIsClient(): boolean {
    return useSyncExternalStore(
        subscribe,
        () => true,
        () => false,
    );
}

function Placeholder({ children }: { children?: ReactNode }) {
    return (
        <div className="flex size-full items-center justify-center bg-gray-100 text-sm text-gray-500 dark:bg-gray-700/40 dark:text-gray-400">
            {children}
        </div>
    );
}

/**
 * The location's pin and its check-in radius on an OpenStreetMap map. Read-only for now. Leaflet
 * is loaded on the client only, since it touches `window` and the app renders on the server too.
 */
export default function LocationMap({
    point,
    className = 'h-64',
}: {
    point: LocationMapPoint;
    /** Height of the map; it always fills the width. */
    className?: string;
}) {
    const isClient = useIsClient();
    const { latitude, longitude, maxDistanceAllowed } = point;
    const hasCoordinates = latitude !== null && longitude !== null;

    return (
        <figure>
            <div
                className={`overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700/60 ${className}`}
            >
                {!hasCoordinates ? (
                    <Placeholder>
                        <span className="inline-flex items-center gap-2">
                            <MapPinOff className="size-4" aria-hidden />
                            No coordinates on file
                        </span>
                    </Placeholder>
                ) : isClient ? (
                    <Suspense
                        fallback={
                            <div className="size-full animate-pulse bg-gray-100 dark:bg-gray-700/40" />
                        }
                    >
                        <LeafletMap
                            latitude={latitude}
                            longitude={longitude}
                            radiusMiles={maxDistanceAllowed}
                        />
                    </Suspense>
                ) : (
                    <div className="size-full animate-pulse bg-gray-100 dark:bg-gray-700/40" />
                )}
            </div>
            {hasCoordinates && (
                <figcaption className="mt-2 flex flex-wrap items-center justify-between gap-2 text-xs text-gray-500 dark:text-gray-400">
                    <span className="font-mono">
                        {latitude.toFixed(6)}, {longitude.toFixed(6)}
                        <span className="font-sans">
                            {' '}
                            · {maxDistanceAllowed} mi check-in radius
                        </span>
                    </span>
                    <a
                        href={`https://www.google.com/maps/search/?api=1&query=${latitude},${longitude}`}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex items-center gap-1 font-medium text-brand-green hover:underline"
                    >
                        Open in Google Maps
                        <ExternalLink className="size-3" aria-hidden />
                    </a>
                </figcaption>
            )}
        </figure>
    );
}
