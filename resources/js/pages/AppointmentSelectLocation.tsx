import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { gate } from '@/actions/App/Http/Controllers/AppointmentController';
import AlertBanner from '@/components/AlertBanner';
import PublicLayout from '@/layouts/PublicLayout';

interface AppointmentSelectLocation {
    pageTitle: string;
    genericError: string;
    selectAnAppointmentLocation: string;
    closed: string;
    open: string;
    closingSoon: string;
    specialHoursToday: string;
    closedToday: string;
    yourUsual: string;
}

interface Translations {
    appointmentSelectLocation: AppointmentSelectLocation;
    publicNavigation: { close: string };
}

interface PageProps {
    locations: Location[];
    usualLocationId: string | null;
    translations: Translations;
    errors: { uuid?: string };
    [key: string]: unknown;
}

interface Location {
    id: string;
    name: string;
    address: string;
    todayOpenCloseTime: string | null;
    isOpen: boolean;
    hasOverride: boolean;
    reason: string | null;
    isOverrideClosure: boolean;
    isClosingSoon: boolean;
}

export default function AppointmentSelectLocation() {
    // Translations
    const { translations, locations, usualLocationId, errors } =
        usePage<PageProps>().props;
    const pageTranslations: AppointmentSelectLocation =
        translations.appointmentSelectLocation;

    const [submittingLocationId, setSubmittingLocationId] = useState<
        string | null
    >(null);
    const [appointmentError, setAppointmentError] = useState<string | null>(
        errors.uuid ?? null,
    );

    const handleSelectLocation = (locationId: string) => {
        setAppointmentError(null);
        setSubmittingLocationId(locationId);

        router.post(
            gate.url({ uuid: locationId }),
            {},
            {
                onError: (errors) => {
                    setAppointmentError(
                        errors[Object.keys(errors)[0]] ??
                            pageTranslations.genericError,
                    );
                    console.error(errors);
                    setSubmittingLocationId(null);
                },
                onFinish: () => {
                    setSubmittingLocationId(null);
                },
            },
        );
    };

    return (
        <PublicLayout>
            <Head title={pageTranslations.pageTitle} />

            <div className="px-4 py-3">
                <div className="mx-auto max-w-md">
                    <h1 className="mb-6 text-3xl font-bold text-brand-grey dark:text-gray-100">
                        {pageTranslations.selectAnAppointmentLocation}:
                    </h1>

                    <AlertBanner
                        type="error"
                        open={!!appointmentError}
                        onClose={() => setAppointmentError(null)}
                        closeLabel={translations.publicNavigation.close}
                        className="mb-3"
                    >
                        {appointmentError}
                    </AlertBanner>

                    <div className="space-y-3">
                        {/* Skeleton */}
                        {locations.length === 0 &&
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
                        {locations.length > 0 &&
                            locations.map((location, idx) => (
                                <button
                                    key={location.id}
                                    onClick={() =>
                                        handleSelectLocation(location.id)
                                    }
                                    disabled={submittingLocationId !== null}
                                    className="relative block w-full cursor-pointer focus:outline-none disabled:opacity-60"
                                    aria-label={`Book appointment at ${location.name}, ${location.address}`}
                                >
                                    <div
                                        className={`flex min-h-22 items-stretch rounded-lg border border-l-4 border-gray-200 border-l-brand-green bg-white text-sm font-medium shadow-sm transition hover:border-brand-green/60 dark:border-gray-700/60 dark:border-l-brand-green/60 dark:bg-gray-800 dark:hover:border-brand-green/50`}
                                    >
                                        {/* Text */}
                                        <div className="flex flex-1 flex-col justify-center gap-0.5 p-4 text-left">
                                            {location.hasOverride &&
                                            !location.isOpen &&
                                            location.reason ? (
                                                <div className="mb-3">
                                                    <span
                                                        title={
                                                            location.reason ??
                                                            ''
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
                                                        title={location.reason}
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
                                            ) : null}

                                            <div
                                                className={`font-semibold ${idx === 0 ? 'text-gray-900 dark:text-gray-100' : 'text-gray-800 dark:text-gray-100'}`}
                                            >
                                                {location.name}
                                            </div>
                                            <div className="text-sm text-gray-600 dark:text-gray-300">
                                                {location.address}
                                            </div>

                                            {/* Hours + Your usual */}
                                            <div className="mt-1 flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                                <span
                                                    aria-label={`Today's hours of operation: ${location.todayOpenCloseTime}`}
                                                >
                                                    {
                                                        location.todayOpenCloseTime
                                                    }
                                                </span>

                                                {location.id ===
                                                    usualLocationId && (
                                                    <span
                                                        aria-label={
                                                            pageTranslations.yourUsual
                                                        }
                                                        className="inline-flex rounded-full bg-blue-500/10 px-2.5 py-1 text-xs font-medium text-blue-600 dark:text-blue-400"
                                                    >
                                                        {
                                                            pageTranslations.yourUsual
                                                        }
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                </button>
                            ))}
                    </div>
                </div>
            </div>
        </PublicLayout>
    );
}
