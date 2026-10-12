import { Head, Link, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    CalendarPlus,
    ChevronRight,
    Clock,
    IdCard,
    MapPin,
    PackageOpen,
    Truck,
} from 'lucide-react';
import type { ComponentType, ReactNode } from 'react';

import EmptyState from '@/components/EmptyState';
import StatusBadge from '@/components/StatusBadge';
import AccountLayout from '@/layouts/AccountLayout';
import { profile as accountProfile } from '@/routes/account';
import appointment from '@/routes/appointment';
import checkIn from '@/routes/checkIn';

// ── Types ────────────────────────────────────────────────────────────────────

interface NextAppointment {
    referenceNumber: string;
    locationName: string;
    monthShort: string;
    day: string;
    time: string;
}

interface RecentCheckIn {
    referenceNumber: string;
    locationName: string;
    date: string;
    status: string;
}

interface AccountTranslations {
    greetingMorning: string;
    greetingAfternoon: string;
    greetingEvening: string;
    checkInNow: string;
    checkInSubtitle: string;
    nextAppointmentTitle: string;
    scheduled: string;
    noAppointment: string;
    noAppointmentSubtitle: string;
    bookAppointment: string;
    recentTitle: string;
    noRecent: string;
    noRecentSubtitle: string;
    reference: string;
    statusPending: string;
    statusCompleted: string;
    statusCancelled: string;
    nudgeAddLicenseTitle: string;
    nudgeAddLicenseBody: string;
    nudgeAddLicenseCta: string;
    nudgeLicenseExpiredTitle: string;
    nudgeLicenseExpiredBody: string;
    nudgeLicenseExpiredCta: string;
}

interface PageProps {
    currentLocale: string;
    hasLicense: boolean;
    licenseExpired: boolean;
    nextAppointment: NextAppointment | null;
    recentCheckIns: RecentCheckIn[];
    translations: { account: AccountTranslations };
    [key: string]: unknown;
}

// ── Status label ─────────────────────────────────────────────────────────────

function statusLabel(status: string, t: AccountTranslations): string {
    const labels: Record<string, string> = {
        pending: t.statusPending,
        completed: t.statusCompleted,
        cancelled: t.statusCancelled,
    };

    return labels[status] ?? t.statusPending;
}

// ── Section heading ──────────────────────────────────────────────────────────

function SectionHeading({ children }: { children: string }) {
    return (
        <h2 className="mb-3 px-1 text-xs font-bold tracking-widest text-brand-green uppercase">
            {children}
        </h2>
    );
}

// ── Nudge card ───────────────────────────────────────────────────────────────

function NudgeCard({
    icon: Icon,
    title,
    body,
    action,
}: {
    icon: ComponentType<{ className?: string }>;
    title: string;
    body: string;
    action: ReactNode;
}) {
    return (
        <div className="flex items-start gap-3 rounded-2xl border border-amber-200/70 bg-amber-50/70 p-4 dark:border-amber-500/20 dark:bg-amber-500/[0.07]">
            <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-500/15 text-amber-600 dark:text-amber-400">
                <Icon className="h-5 w-5" />
            </span>
            <div className="min-w-0 flex-1">
                <p className="text-sm font-semibold text-brand-grey dark:text-gray-100">
                    {title}
                </p>
                <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                    {body}
                </p>
            </div>
            <div className="shrink-0 self-center">{action}</div>
        </div>
    );
}

// ── Page ─────────────────────────────────────────────────────────────────────

export default function Account() {
    const {
        currentLocale,
        hasLicense,
        licenseExpired,
        nextAppointment,
        recentCheckIns,
        translations,
    } = usePage<PageProps>().props;
    const t = translations.account;

    const hour = new Date().getHours();
    const greeting =
        hour < 12
            ? t.greetingMorning
            : hour < 18
              ? t.greetingAfternoon
              : t.greetingEvening;

    const today = new Intl.DateTimeFormat(currentLocale, {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
    }).format(new Date());

    return (
        <AccountLayout>
            <Head title={t.checkInNow} />

            <div className="mx-auto max-w-2xl space-y-7 px-6 pt-5 pb-12">
                <p className="-mt-1 px-1 text-sm text-gray-400 capitalize dark:text-gray-500">
                    {greeting} · {today}
                </p>

                {/* Primary CTA */}
                <Link
                    href={checkIn.selectLocation().url}
                    className="group relative flex items-center gap-4 overflow-hidden rounded-3xl bg-gradient-to-br from-brand-green to-green-600 p-5 text-white shadow-lg shadow-brand-green/25 transition-all active:scale-[0.99]"
                >
                    <div
                        aria-hidden
                        className="pointer-events-none absolute -top-10 -right-8 h-32 w-32 rounded-full bg-white/15 blur-2xl"
                    />
                    <span className="relative flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-white/20 ring-1 ring-white/25 backdrop-blur-sm">
                        <Truck className="h-7 w-7" />
                    </span>
                    <span className="relative min-w-0 flex-1">
                        <span className="block text-lg font-bold">
                            {t.checkInNow}
                        </span>
                        <span className="mt-0.5 block text-sm text-white/85">
                            {t.checkInSubtitle}
                        </span>
                    </span>
                    <ChevronRight className="relative h-5 w-5 shrink-0 text-white/85 transition-transform group-hover:translate-x-0.5" />
                </Link>

                {/* Smart nudges. No verify-email nudge here: this page sits
                    behind the "verified" middleware, so an unverified driver
                    never reaches it. They get the dedicated notice page. */}
                {(!hasLicense || licenseExpired) && (
                    <div className="space-y-3">
                        {!hasLicense && (
                            <NudgeCard
                                icon={IdCard}
                                title={t.nudgeAddLicenseTitle}
                                body={t.nudgeAddLicenseBody}
                                action={
                                    <Link
                                        href={accountProfile().url}
                                        className="inline-flex items-center rounded-full bg-brand-green px-3 py-1.5 text-xs font-semibold text-white shadow-sm shadow-brand-green/25 transition-colors hover:bg-brand-green/90"
                                    >
                                        {t.nudgeAddLicenseCta}
                                    </Link>
                                }
                            />
                        )}
                        {hasLicense && licenseExpired && (
                            <NudgeCard
                                icon={IdCard}
                                title={t.nudgeLicenseExpiredTitle}
                                body={t.nudgeLicenseExpiredBody}
                                action={
                                    <Link
                                        href={accountProfile().url}
                                        className="inline-flex items-center rounded-full bg-brand-green px-3 py-1.5 text-xs font-semibold text-white shadow-sm shadow-brand-green/25 transition-colors hover:bg-brand-green/90"
                                    >
                                        {t.nudgeLicenseExpiredCta}
                                    </Link>
                                }
                            />
                        )}
                    </div>
                )}

                {/* Next appointment */}
                <section>
                    <SectionHeading>{t.nextAppointmentTitle}</SectionHeading>
                    {nextAppointment ? (
                        <div className="flex items-stretch gap-4 rounded-2xl border border-gray-200/80 bg-white p-4 shadow-sm ring-1 ring-black/[0.02] dark:border-gray-700/60 dark:bg-gray-800/50 dark:ring-white/[0.02]">
                            {/* Event date chip */}
                            <div className="flex w-16 shrink-0 flex-col items-center justify-center rounded-xl bg-brand-green/10 py-2.5 text-brand-green">
                                <span className="text-[11px] font-bold tracking-wide uppercase">
                                    {nextAppointment.monthShort}
                                </span>
                                <span className="text-2xl leading-none font-bold">
                                    {nextAppointment.day}
                                </span>
                            </div>
                            {/* Details */}
                            <div className="flex min-w-0 flex-1 flex-col justify-center">
                                <div className="flex items-center gap-2">
                                    <Clock className="h-4 w-4 shrink-0 text-gray-400" />
                                    <span className="font-semibold text-brand-grey dark:text-gray-100">
                                        {nextAppointment.time}
                                    </span>
                                    <span className="rounded-full bg-brand-green/12 px-2 py-0.5 text-[10px] font-bold tracking-wide text-brand-green uppercase">
                                        {t.scheduled}
                                    </span>
                                </div>
                                <p className="mt-1.5 flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400">
                                    <MapPin className="h-3.5 w-3.5 shrink-0" />
                                    <span className="truncate">
                                        {nextAppointment.locationName}
                                    </span>
                                </p>
                                <p className="mt-1 font-mono text-[11px] text-gray-400 dark:text-gray-500">
                                    {t.reference} #
                                    {nextAppointment.referenceNumber}
                                </p>
                            </div>
                        </div>
                    ) : (
                        <EmptyState
                            icon={CalendarDays}
                            tone="brand"
                            title={t.noAppointment}
                            subtitle={t.noAppointmentSubtitle}
                            action={
                                <Link
                                    href={appointment.selectLocation().url}
                                    className="inline-flex items-center gap-1.5 rounded-full bg-brand-green px-4 py-2 text-sm font-semibold text-white shadow-sm shadow-brand-green/30 transition-colors hover:bg-brand-green/90"
                                >
                                    <CalendarPlus className="h-4 w-4" />
                                    {t.bookAppointment}
                                </Link>
                            }
                        />
                    )}
                </section>

                {/* Recent activity */}
                <section>
                    <SectionHeading>{t.recentTitle}</SectionHeading>
                    {recentCheckIns.length > 0 ? (
                        <ul className="divide-y divide-gray-100 overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm ring-1 ring-black/[0.02] dark:divide-gray-700/50 dark:border-gray-700/60 dark:bg-gray-800/50 dark:ring-white/[0.02]">
                            {recentCheckIns.map((checkIn) => (
                                <li
                                    key={checkIn.referenceNumber}
                                    className="flex items-center gap-3.5 px-4 py-3.5 transition-colors hover:bg-gray-50/60 dark:hover:bg-gray-800/40"
                                >
                                    <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-green/10 text-brand-green">
                                        <MapPin className="h-4 w-4" />
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-semibold text-brand-grey dark:text-gray-100">
                                            {checkIn.locationName}
                                        </p>
                                        <p className="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                                            {checkIn.date} · {t.reference} #
                                            {checkIn.referenceNumber}
                                        </p>
                                    </div>
                                    <StatusBadge
                                        status={checkIn.status}
                                        label={statusLabel(checkIn.status, t)}
                                    />
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <EmptyState
                            icon={PackageOpen}
                            title={t.noRecent}
                            subtitle={t.noRecentSubtitle}
                        />
                    )}
                </section>
            </div>
        </AccountLayout>
    );
}
