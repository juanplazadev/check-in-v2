import { Link, router, useHttp } from '@inertiajs/react';
import { differenceInCalendarDays, parseISO } from 'date-fns';
import { Eye, EyeOff, Mail, Phone } from 'lucide-react';
import { useState } from 'react';

import CheckInLicenseController from '@/actions/App/Http/Controllers/Dashboard/CheckInLicenseController';

import Card from '@/components/dashboard/Card';
import { DetailRow } from '@/components/dashboard/DetailCard';
import RegionLabel from '@/components/dashboard/RegionLabel';
import type {
    CheckInFormFields,
    CheckInRecord,
} from '@/components/dashboard/types';
import { hasAnyRole, useStaffRoles } from '@/hooks/useStaffRoles';
import {
    combinedFieldState,
    fieldState,
    LEGACY_FIELD_HINT,
} from '@/lib/dashboard/checkInFields';
import {
    formatCalendarDate,
    formatPhone,
    initialsOf,
} from '@/lib/dashboard/format';
import dashboardRoutes from '@/routes/dashboard';

/** Days left before a license counts as expiring soon. */
const EXPIRING_SOON_DAYS = 30;

export default function DriverCard({
    checkIn,
    fields,
}: {
    checkIn: CheckInRecord;
    fields: CheckInFormFields;
}) {
    const { driver } = checkIn;
    const roles = useStaffRoles();
    const isAdmin = hasAnyRole(roles, ['admin']);
    // Presentation only: the license route enforces the same roles.
    const canRevealLicense = hasAnyRole(roles, [
        'admin',
        'shipping-manager',
        'shipping',
    ]);

    const licenseState = fieldState(
        fields.showDriversLicenseState,
        driver.licenseState,
    );
    const licenseCountry = fieldState(
        fields.showDriversLicenseCountry,
        driver.licenseCountry,
    );
    const licenseRegion = combinedFieldState([licenseState, licenseCountry]);
    const expiration = fieldState(
        fields.showDriversLicenseExpirationDate,
        driver.licenseExpirationDate,
    );

    const accountPill = (
        <span
            className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ${driver.hasAccount ? 'bg-brand-green/12 text-brand-green' : 'bg-gray-400/15 text-gray-500 dark:text-gray-400'}`}
        >
            {driver.hasAccount ? 'Registered driver' : 'Guest'}
        </span>
    );

    return (
        <Card title="Driver">
            <div className="flex flex-col gap-4 px-5 pt-5 sm:flex-row sm:items-center">
                <span className="flex size-14 shrink-0 items-center justify-center rounded-full bg-gray-100 text-lg font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-200">
                    {initialsOf(driver.name)}
                </span>
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="text-lg font-semibold text-gray-800 dark:text-gray-100">
                            {driver.name}
                        </span>
                        {isAdmin && driver.userUuid !== null ? (
                            <Link
                                href={dashboardRoutes.users.show.url(
                                    driver.userUuid,
                                )}
                                className="hover:opacity-80"
                            >
                                {accountPill}
                            </Link>
                        ) : (
                            accountPill
                        )}
                    </div>
                    <div className="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                        <a
                            href={`tel:${driver.cellphone}`}
                            className="inline-flex items-center gap-1.5 text-gray-600 hover:text-brand-green dark:text-gray-300"
                        >
                            <Phone className="size-3.5 text-gray-400" />
                            {formatPhone(driver.cellphone)}
                        </a>
                        {driver.email !== null && (
                            <a
                                href={`mailto:${driver.email}`}
                                className="inline-flex min-w-0 items-center gap-1.5 text-gray-600 hover:text-brand-green dark:text-gray-300"
                            >
                                <Mail className="size-3.5 shrink-0 text-gray-400" />
                                <span className="truncate">{driver.email}</span>
                            </a>
                        )}
                    </div>
                </div>
            </div>

            <dl className="mt-2 divide-y divide-gray-100 px-5 text-sm dark:divide-gray-700/60">
                <DetailRow
                    label="Driver's license"
                    hint={
                        licenseRegion === 'legacy'
                            ? LEGACY_FIELD_HINT
                            : undefined
                    }
                >
                    <span className="inline-flex flex-wrap items-center gap-2 sm:justify-end">
                        {canRevealLicense ? (
                            <RevealableLicense
                                checkInUuid={checkIn.uuid}
                                lastFour={driver.licenseLastFour}
                            />
                        ) : (
                            <span className="font-mono">
                                •••• {driver.licenseLastFour}
                            </span>
                        )}
                        {licenseRegion !== 'hidden' && (
                            <RegionLabel
                                state={
                                    licenseState === 'hidden'
                                        ? null
                                        : driver.licenseState
                                }
                                country={
                                    licenseCountry === 'hidden'
                                        ? null
                                        : driver.licenseCountry
                                }
                                className="text-xs font-normal text-gray-500 dark:text-gray-400"
                            />
                        )}
                    </span>
                </DetailRow>

                <DetailRow label="Check-in language">
                    <span className="inline-flex items-center gap-2">
                        {languageName(checkIn.locale)}
                        <span className="rounded bg-gray-100 px-1 py-px text-[10px] leading-tight font-semibold text-gray-500 uppercase dark:bg-gray-700/60 dark:text-gray-400">
                            {checkIn.locale}
                        </span>
                    </span>
                </DetailRow>

                {expiration !== 'hidden' && (
                    <DetailRow
                        label="License expires"
                        hint={
                            expiration === 'legacy'
                                ? LEGACY_FIELD_HINT
                                : undefined
                        }
                    >
                        {driver.licenseExpirationDate !== null ? (
                            <LicenseExpiration
                                date={driver.licenseExpirationDate}
                                submittedAt={checkIn.createdAt}
                            />
                        ) : null}
                    </DetailRow>
                )}
            </dl>
        </Card>
    );
}

/**
 * The masked license number with a button that fetches the full one. The
 * number is fetched once, on the first reveal, since every fetch is logged on
 * the check-in timeline; hiding it again only toggles it locally.
 */
function RevealableLicense({
    checkInUuid,
    lastFour,
}: {
    checkInUuid: string;
    lastFour: string;
}) {
    const [number, setNumber] = useState<string | null>(null);
    const [visible, setVisible] = useState(false);
    const [failed, setFailed] = useState(false);
    const { post, processing } = useHttp<
        Record<string, never>,
        { number: string }
    >({});

    const toggle = () => {
        if (number !== null) {
            setVisible((current) => !current);

            return;
        }

        setFailed(false);
        post(CheckInLicenseController.url(checkInUuid))
            .then((response) => {
                setNumber(response.number);
                setVisible(true);
                // The reveal added a timeline row; pull it in.
                router.reload({ only: ['checkIn'] });
            })
            .catch(() => setFailed(true));
    };

    const Icon = visible ? EyeOff : Eye;

    return (
        <span className="inline-flex items-center gap-1.5">
            <span className="font-mono">
                {visible && number !== null ? number : `•••• ${lastFour}`}
            </span>
            <button
                type="button"
                onClick={toggle}
                disabled={processing}
                aria-label={
                    visible ? 'Hide license number' : 'Show license number'
                }
                title={visible ? 'Hide license number' : 'Show license number'}
                className="rounded p-0.5 text-gray-400 hover:text-brand-green disabled:opacity-50 dark:text-gray-500"
            >
                <Icon className="size-3.5" />
            </button>
            {failed && (
                <span className="text-xs text-rose-600 dark:text-rose-400">
                    Could not load
                </span>
            )}
        </span>
    );
}

/** A locale code in English for staff, e.g. 'es' reads "Spanish". Falls back to the code. */
function languageName(locale: string): string {
    try {
        return (
            new Intl.DisplayNames(['en'], { type: 'language' }).of(locale) ??
            locale
        );
    } catch {
        return locale;
    }
}

/** The expiration date, flagged against the day the driver checked in. */
function LicenseExpiration({
    date,
    submittedAt,
}: {
    date: string;
    submittedAt: string;
}) {
    const daysLeft = differenceInCalendarDays(
        parseISO(date),
        new Date(submittedAt),
    );

    return (
        <span className="inline-flex items-center gap-2">
            {formatCalendarDate(date)}
            {daysLeft < 0 && (
                <span className="rounded-full bg-rose-500/12 px-2 py-0.5 text-[11px] font-semibold text-rose-700 dark:text-rose-400">
                    Expired
                </span>
            )}
            {daysLeft >= 0 && daysLeft <= EXPIRING_SOON_DAYS && (
                <span className="rounded-full bg-amber-500/12 px-2 py-0.5 text-[11px] font-semibold text-amber-700 dark:text-amber-400">
                    Expires soon
                </span>
            )}
        </span>
    );
}
