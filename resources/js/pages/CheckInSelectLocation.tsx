import { Head, router, usePage, useHttp } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { gate } from '@/actions/App/Http/Controllers/CheckInController';
import CheckInDistanceController from '@/actions/App/Http/Controllers/CheckInDistanceController';
import AlertBanner from '@/components/AlertBanner';
import { useGeolocation } from '@/hooks/useGeolocation';
import PublicLayout from '@/layouts/PublicLayout';

interface CheckInTranslations {
    selectACheckInLocation: string;
    closed: string;
    open: string;
    nearest: string;
    closingSoon: string;
    specialHoursToday: string;
    closedToday: string;
    geolocationErrorMessage: string;
    geolocationErrorTitle: string;
    geolocationNotSupportedMessage: string;
    geolocationNotSupportedTitle: string;
    fetchDistancesErrorTitle: string;
    fetchDistancesErrorMessage: string;
    checkInUnavailable: string;
    retryLocation: string;
}

interface Translations {
    checkInSelectLocation: CheckInTranslations;
}

interface DistanceLocation {
    id: string;
    userDistance: number;
}

interface DriverCoordinates {
    latitude: number;
    longitude: number;
}

interface Alert {
    type: 'error' | 'warning' | 'success';
    title: string;
    message: string;
}

interface PageProps {
    locations: Location[];
    translations: Translations;
    [key: string]: unknown;
}

interface Location {
    id: string;
    name: string;
    address: string;
    maxDistanceAllowed: number;
    todayOpenCloseTime: string | null;
    isOpen: boolean;
    hasOverride: boolean;
    reason: string | null;
    isOverrideClosure: boolean;
    isClosingSoon: boolean;
    userDistance?: number | null; // Retrieved after page render
}

export default function SelectLocation() {
    // Translations
    const { translations, locations } = usePage<PageProps>().props;
    const pageTranslations: CheckInTranslations = translations.checkInSelectLocation;

    // User coordinates
    const [sortedLocations, setSortedLocations] = useState<Location[] | null>(null);
    const { coords, loading, error, warning, retry } = useGeolocation();

    const { data, post } = useHttp({'latitude': 0,'longitude': 0});

    const fetchDistances = async (position: DriverCoordinates) => {
        await post(CheckInDistanceController().url, {
            onBefore: () => {
                data.latitude = position.latitude;
                data.longitude = position.longitude;
            },
            onSuccess: (response: any) => {
                const distanceMap = new Map(
                    response.locations.map(
                        ({ id, userDistance }: DistanceLocation) => [
                            id,
                            userDistance,
                        ],
                    ),
                );

                const merged = response.locations
                    .map(({ id }: { id: string }) => {
                        const location = locations.find((loc) => loc.id === id);

                        return location
                            ? {
                                  ...location,
                                  userDistance: distanceMap.get(id) ?? null,
                              }
                            : null;
                    })
                    .filter(Boolean);

                setSortedLocations(merged);
            },
            onError: () => setFetchError(true),
            onNetworkError: () => setFetchError(true),
        });
    };

    // Alert banner
    const getAlert = (): Alert | null => {
        if (error) {
            return {
                type: 'error',
                title: pageTranslations.geolocationErrorTitle,
                message: pageTranslations.geolocationErrorMessage,
            };
        }

        if (warning) {
            return {
                type: 'warning',
                title: pageTranslations.geolocationNotSupportedTitle,
                message: pageTranslations.geolocationNotSupportedMessage,
            };
        }

        if (fetchError) {
            return {
                type: 'error',
                title: pageTranslations.fetchDistancesErrorTitle,
                message: pageTranslations.fetchDistancesErrorMessage,
            };
        }

        if (checkInError) {
            return {
                type: 'error',
                title: pageTranslations.checkInUnavailable,
                message: checkInError,
            };
        }

        return null;
    };

    const [fetchError, setFetchError] = useState(false);
    const [checkInError, setCheckInError] = useState<string | null>(null);

    const [submittingLocationId, setSubmittingLocationId] = useState<string | null>(null);

    const [dismissed, setDismissed] = useState(false);
    const alert = dismissed ? null : getAlert();

    /*
     * Check-in is gated on verified proximity, so with no coordinates or no distances there is nothing
     * selectable here. The list stays hidden rather than offering locations the gate would refuse, and
     * the driver gets a way to try again instead of skeletons that never resolve.
     *
     * retry() also covers the distance-fetch failure: it republishes the cached coordinates, which
     * re-triggers the effect below and with it fetchDistances.
     */
    const needsRetry = !loading && (error !== null || warning !== null || fetchError);

    const handleSelectLocation = (locationId: string, isRetry = false) => {
        setCheckInError(null);
        setDismissed(false);
        setSubmittingLocationId(locationId);

        router.post(
            gate.url({ uuid: locationId }),
            {},
            {
                onError: (errors) => {
                    // Session expired or missing coords — re-fetch to restore session, then retry once
                    if (!isRetry && errors.userCoords && coords) {
                        try {
                            fetchDistances(coords).then(() => {
                                handleSelectLocation(locationId, true)
                            });
                        } catch {
                            setCheckInError(
                                'Something went wrong. Please try again.',
                            );
                            setSubmittingLocationId(null);
                        }

                        return;
                    }

                    setCheckInError(
                        errors[Object.keys(errors)[0]] ??
                            'Something went wrong. Please try again.',
                    );

                    console.error(errors);
                    setSubmittingLocationId(null);
                },
                onFinish: () => {
                    if (isRetry) {
                        setSubmittingLocationId(null);
                    }
                },
            },
        );
    };

    useEffect(() => {
        if (!coords) {
            return;
        }

        fetchDistances(coords);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [coords]);

    return (
        <PublicLayout>
            <Head title="Check-In: Select Location" />

            <div className="px-4 py-3">
                <div className="mx-auto max-w-md">
                    <h1 className="mb-6 text-3xl font-bold text-brand-grey dark:text-gray-100">
                        {pageTranslations.selectACheckInLocation}:
                    </h1>

                    <div className="space-y-3">
                        {/* Alert Banner for errors, warning and etc. */}
                        <AlertBanner
                            type={alert?.type ?? 'error'}
                            title={alert?.title}
                            open={alert !== null}
                            onClose={() => setDismissed(true)}
                        >
                            {alert?.message}
                        </AlertBanner>

                        {/* Retry, when there are no coordinates to sort or gate by */}
                        {needsRetry && (
                            <button
                                type="button"
                                onClick={() => {
                                    setDismissed(false);
                                    setFetchError(false);
                                    retry();
                                }}
                                className="min-h-11 w-full cursor-pointer rounded-lg border border-gray-200 bg-white px-5 text-sm font-semibold text-brand-grey shadow-sm transition hover:border-gray-300 dark:border-gray-700/60 dark:bg-gray-800 dark:text-gray-100 dark:hover:border-gray-600"
                            >
                                {pageTranslations.retryLocation}
                            </button>
                        )}

                        {/* Skeleton */}
                        {!needsRetry &&
                            (loading || sortedLocations === null) &&
                            Array.from({ length: 3 }).map((_, i) => (
                                <div
                                    key={i}
                                    className="mb-3 flex animate-pulse items-start justify-between space-x-4 rounded-lg border border-gray-200 bg-white p-4 shadow dark:border-gray-700/60 dark:bg-gray-800"
                                >
                                    <div className="mr-6 flex w-6 flex-col items-center">
                                        <div className="h-6 w-6 rounded-full bg-gray-300 dark:bg-gray-700" />
                                        <div className="mt-2 h-3 w-10 rounded bg-gray-300 dark:bg-gray-700" />
                                    </div>
                                    <div className="flex-1 space-y-2">
                                        <div className="h-4 w-3/4 rounded bg-gray-300 dark:bg-gray-700" />
                                        <div className="h-3 w-1/2 rounded bg-gray-300 dark:bg-gray-700" />
                                        <div className="h-2 w-1/4 rounded bg-gray-300 dark:bg-gray-700" />
                                    </div>
                                    <div className="h-5 w-14 self-start rounded-full bg-gray-300 dark:bg-gray-700" />
                                </div>
                            ))}
                        {/* Locations List */}
                        {sortedLocations !== null &&
                            sortedLocations.map((location, idx) => {

                                return (
                                    <button
                                        key={location.id}
                                        onClick={() =>
                                            handleSelectLocation(location.id)
                                        }
                                        disabled={submittingLocationId !== null}
                                        className="relative block w-full focus:outline-none disabled:opacity-60"
                                        aria-label={`Check-in at ${location.name}, ${location.address}`}
                                    >
                                        <div
                                            className={`flex min-h-22 items-stretch rounded-lg border p-4 text-sm font-medium shadow-sm transition ${
                                                idx === 0
                                                    ? 'border-brand-green/40 bg-white dark:border-brand-green/30 dark:bg-gray-800'
                                                    : 'border-gray-200 bg-white hover:border-gray-300 dark:border-gray-700/60 dark:bg-gray-800 dark:hover:border-gray-600'
                                            }`}
                                        >
                                            {/* Icon + Distance */}
                                            <div className="mr-4 flex w-10 shrink-0 flex-col items-center justify-center">
                                                <svg
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 384 512"
                                                    className={`h-6 w-6 fill-current ${idx === 0 ? 'text-brand-green' : 'text-gray-400 dark:text-gray-500'}`}
                                                >
                                                    <path d="M215.7 499.2C267 435 384 279.4 384 192C384 86 298 0 192 0S0 86 0 192c0 87.4 117 243 168.3 307.2c12.3 15.3 35.1 15.3 47.4 0zM192 128a64 64 0 1 1 0 128 64 64 0 1 1 0-128z" />
                                                </svg>
                                                <span
                                                    aria-label={`Distance: ${location.userDistance} miles`}
                                                    className="mt-1 min-w-13 text-center text-xs text-gray-500 dark:text-gray-400"
                                                >
                                                    {location.userDistance !=
                                                    null
                                                        ? location.userDistance.toLocaleString(
                                                              'en-US',
                                                              {
                                                                  minimumFractionDigits: 1,
                                                                  maximumFractionDigits: 1,
                                                              },
                                                          ) + ' mi'
                                                        : '- mi'}
                                                </span>
                                            </div>

                                            {/* Text */}
                                            <div className="flex flex-1 flex-col justify-center gap-0.5 text-left">
                                                {location.hasOverride &&
                                                  !location.isOpen &&
                                                  location.reason ? (
                                                    <div className="mb-3">
                                                        <span
                                                            title={
                                                                location.reason
                                                            }
                                                            aria-label={`Closed today: ${location.reason}`}
                                                            className="inline-flex items-center gap-1 rounded-full bg-red-500/15 px-2.5 py-1 text-xs font-medium text-red-700 dark:text-red-400"
                                                        >
                                                            <svg
                                                                xmlns="http://www.w3.org/2000/svg"
                                                                viewBox="0 0 16 16"
                                                                className="h-3 w-3 fill-current"
                                                            >
                                                                <path d="M8 1a7 7 0 1 0 0 14A7 7 0 0 0 8 1zm0 3.5a.75.75 0 0 1 .75.75v3a.75.75 0 0 1-1.5 0v-3A.75.75 0 0 1 8 4.5zm0 6.5a.875.875 0 1 1 0-1.75A.875.875 0 0 1 8 11z" />
                                                            </svg>
                                                            {location.reason}
                                                        </span>
                                                    </div>
                                                ) : location.hasOverride &&
                                                  location.isOpen &&
                                                  location.reason ? (
                                                    <div className="mb-3">
                                                        <span
                                                            title={
                                                                location.reason
                                                            }
                                                            aria-label={`Special Hours: ${location.reason}`}
                                                            className="inline-flex items-center gap-1 rounded-full bg-amber-500/15 px-2.5 py-1 text-xs font-medium text-amber-700 dark:text-amber-400"
                                                        >
                                                            <svg
                                                                xmlns="http://www.w3.org/2000/svg"
                                                                viewBox="0 0 16 16"
                                                                className="h-3 w-3 fill-current"
                                                            >
                                                                <path d="M8 1a7 7 0 1 0 0 14A7 7 0 0 0 8 1zm0 3.5a.75.75 0 0 1 .75.75v3a.75.75 0 0 1-1.5 0v-3A.75.75 0 0 1 8 4.5zm0 6.5a.875.875 0 1 1 0-1.75A.875.875 0 0 1 8 11z" />
                                                            </svg>
                                                            {location.reason}
                                                        </span>
                                                    </div>
                                                ) : location.isClosingSoon ? (
                                                    <div className="mb-3">
                                                        <span
                                                            aria-label="Closing soon"
                                                            className="inline-flex items-center gap-1 rounded-full bg-purple-500/15 px-2.5 py-1 text-xs font-medium text-purple-700 dark:text-purple-400"
                                                        >
                                                            <svg
                                                                xmlns="http://www.w3.org/2000/svg"
                                                                viewBox="0 0 16 16"
                                                                className="h-3 w-3 fill-current"
                                                            >
                                                                <path d="M8 1a7 7 0 1 0 0 14A7 7 0 0 0 8 1zm0 2a.75.75 0 0 1 .75.75V8a.75.75 0 0 1-.22.53l-2 2a.75.75 0 1 1-1.06-1.06L7.25 7.69V3.75A.75.75 0 0 1 8 3z" />
                                                            </svg>
                                                            {
                                                                pageTranslations.closingSoon
                                                            }
                                                        </span>
                                                    </div>
                                                ) : null}

                                                <div
                                                    className={`font-semibold ${idx === 0 ? 'text-gray-900 dark:text-gray-100' : 'text-gray-800 dark:text-gray-100'}`}
                                                >
                                                    {location.name}
                                                </div>
                                                <div className="text-sm text-gray-600 dark:text-gray-300">
                                                    {location.address}
                                                </div>

                                                {/* Hours + Open/Closed + Nearest */}
                                                <div className="mt-1 flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                                    <span
                                                        aria-label={`Hours of operation: ${location.todayOpenCloseTime}`}
                                                    >
                                                        {location.todayOpenCloseTime ??
                                                            (location.hasOverride
                                                                ? pageTranslations.closedToday
                                                                : '')}
                                                    </span>

                                                    {/* Open/Closed badge */}
                                                    {location.isOpen ? (
                                                        <span className="inline-flex rounded-full bg-green-500/10 px-2.5 py-1 text-xs font-medium text-brand-green">
                                                            {
                                                                pageTranslations.open
                                                            }
                                                        </span>
                                                    ) : (
                                                        <span className="inline-flex rounded-full bg-red-500/20 px-2.5 py-1 text-xs font-medium text-red-700">
                                                            {
                                                                pageTranslations.closed
                                                            }
                                                        </span>
                                                    )}

                                                    {/* Nearest badge */}
                                                    {idx === 0 && (
                                                        <span
                                                            aria-label="Nearest location"
                                                            className="inline-flex rounded-full bg-blue-500/10 px-2.5 py-1 text-xs font-medium text-blue-600 dark:text-blue-400"
                                                        >
                                                            {
                                                                pageTranslations.nearest
                                                            }
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    </button>
                                );
                            })}
                    </div>
                </div>
            </div>
        </PublicLayout>
    );
}
